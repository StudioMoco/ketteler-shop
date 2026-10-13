<?php declare(strict_types=1);

namespace Ketteler\Theme\Weiterleitung;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alte Adressen des bisherigen Shops (ketteler-verlag.de) leiten mit 301 auf die neuen Seiten. Liste in
 * Resources/config/weiterleitungen.json (alt: Pfad mit optionalen Parametern, z. B. „/?listview&link=0205000“;
 * ziel: Kategorie-Schlüssel aus ketteler:einrichten, optional mit #Sprungmarke). Ziel ist die SEO-Adresse der
 * Kategorie im aktuellen Verkaufskanal. Übersicht: docs/plan.md, Abschnitt Weiterleitungen.
 */
class AlteAdressen implements EventSubscriberInterface
{
    /** @var list<array{alt: string, ziel: string}>|null */
    private ?array $liste = null;

    public function __construct(private readonly Connection $db)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Vor dem Routing (RouterListener: 32), damit unbekannte alte Pfade nicht als 404 enden
        return [KernelEvents::REQUEST => ['weiterleiten', 40]];
    }

    public function weiterleiten(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }
        $treffer = self::finden($this->liste(), $request->getPathInfo(), $request->query->all());
        if ($treffer === null) {
            return;
        }
        [$schluessel, $marke] = array_pad(explode('#', $treffer, 2), 2, null);
        $pfad = $this->seoPfad(Uuid::fromStringToHex('ketteler.kategorie.' . $schluessel), $request);
        if ($pfad === null) {
            return;
        }
        $basis = rtrim((string) $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL, ''), '/');
        $event->setResponse(new RedirectResponse($request->getSchemeAndHttpHost() . $basis . '/' . ltrim($pfad, '/') . ($marke ? '#' . $marke : ''), 301));
    }

    /**
     * Ziel zur alten Adresse: Pfad ohne Groß-/Kleinschreibung und ohne Schrägstrich am Ende; alle Parameter der alten
     * Adresse müssen in der Anfrage vorkommen (mit gleichem Wert, leerer Wert = nur vorhanden).
     *
     * @param list<array{alt: string, ziel: string}> $liste
     * @param array<string, mixed> $parameter
     */
    public static function finden(array $liste, string $pfad, array $parameter): ?string
    {
        $pfad = strtolower(rtrim($pfad, '/')) ?: '/';
        foreach ($liste as $eintrag) {
            $teile = parse_url($eintrag['alt']);
            $altPfad = strtolower(rtrim($teile['path'] ?? '/', '/')) ?: '/';
            if ($altPfad !== $pfad) {
                continue;
            }
            $soll = [];
            foreach (explode('&', $teile['query'] ?? '') as $paar) {
                if ($paar !== '') {
                    [$k, $v] = array_pad(explode('=', $paar, 2), 2, '');
                    $soll[urldecode($k)] = urldecode($v);
                }
            }
            if ($soll === [] && $altPfad === '/') {
                continue; // die Startseite selbst nie umleiten
            }
            foreach ($soll as $k => $v) {
                if (!\array_key_exists($k, $parameter) || ($v !== '' && (string) $parameter[$k] !== $v)) {
                    continue 2;
                }
            }

            return $eintrag['ziel'];
        }

        return null;
    }

    private function seoPfad(string $kategorie, Request $request): ?string
    {
        $kanal = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if (!\is_string($kanal) || !Uuid::isValid($kanal)) {
            return null;
        }
        $pfad = $this->db->fetchOne(
            'SELECT seo_path_info FROM seo_url WHERE foreign_key = :k AND route_name = :r AND sales_channel_id = :s
             AND is_canonical = 1 AND is_deleted = 0 ORDER BY language_id = :l DESC LIMIT 1',
            ['k' => Uuid::fromHexToBytes($kategorie), 'r' => 'frontend.navigation.page', 's' => Uuid::fromHexToBytes($kanal),
             'l' => Uuid::fromHexToBytes((string) ($request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID) ?: Defaults::LANGUAGE_SYSTEM))]
        );
        if (\is_string($pfad) && $pfad !== '') {
            return $pfad;
        }
        $vorhanden = $this->db->fetchOne('SELECT 1 FROM category WHERE id = :k', ['k' => Uuid::fromHexToBytes($kategorie)]);

        return $vorhanden ? 'navigation/' . $kategorie : null;
    }

    /** @return list<array{alt: string, ziel: string}> */
    private function liste(): array
    {
        return $this->liste ??= json_decode((string) file_get_contents(__DIR__ . '/../Resources/config/weiterleitungen.json'), true, 512, \JSON_THROW_ON_ERROR)['weiterleitungen'];
    }
}
