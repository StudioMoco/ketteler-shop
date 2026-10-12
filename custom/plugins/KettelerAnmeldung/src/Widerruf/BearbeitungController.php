<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Widerruf;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verwaltung: Widerruf an einer Bestellung als bearbeitet markieren oder das zurücknehmen (Knopf in der Bestellung).
 * Speichert Zeit und Bearbeiter, tauscht das Kennzeichen „Widerruf eingegangen“ gegen „Widerruf bearbeitet“ (und zurück)
 * und protokolliert jede Änderung im Verlauf an der Bestellung und im Shop-Protokoll.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class BearbeitungController
{
    public function __construct(
        private readonly EntityRepository $bestellungen,
        private readonly EntityRepository $bestellungKennzeichen,
        private readonly EntityRepository $benutzer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/api/_action/ketteler/widerruf/{orderId}/bearbeitung', name: 'api.action.ketteler.widerruf.bearbeitung', defaults: ['_acl' => ['order:update']], methods: ['POST'])]
    public function umschalten(string $orderId, Request $request, Context $context): JsonResponse
    {
        if (!Uuid::isValid($orderId)) {
            return new JsonResponse(['fehler' => 'Ungültige Bestellung.'], 400);
        }
        $daten = json_decode((string) $request->getContent(), true);
        if (!\is_array($daten) || !\is_bool($daten['bearbeitet'] ?? null)) {
            return new JsonResponse(['fehler' => 'Feld „bearbeitet“ (true oder false) fehlt.'], 400);
        }
        $bestellung = $this->bestellungen->search(new Criteria([$orderId]), $context)->first();
        if (!$bestellung instanceof OrderEntity) {
            return new JsonResponse(['fehler' => 'Bestellung nicht gefunden.'], 404);
        }
        $felder = $bestellung->getCustomFields() ?? [];
        if (empty($felder[WiderrufsanfrageRoute::FELD_LISTE])) {
            return new JsonResponse(['fehler' => 'An dieser Bestellung ist kein Widerruf eingegangen.'], 409);
        }
        [$name, $benutzerId] = $this->bearbeiter($context);
        try {
            $stand = Bearbeitung::umschalten($felder[Bearbeitung::FELD] ?? null, $daten['bearbeitet'], new \DateTimeImmutable(), $name, $benutzerId);
        } catch (\DomainException $e) {
            return new JsonResponse(['fehler' => $e->getMessage()], 409);
        }

        [$weg, $hin] = $daten['bearbeitet']
            ? [WiderrufsanfrageRoute::tagId(), ['id' => Bearbeitung::tagId(), 'name' => Bearbeitung::TAG_BEARBEITET]]
            : [Bearbeitung::tagId(), ['id' => WiderrufsanfrageRoute::tagId(), 'name' => 'Widerruf eingegangen']];
        $this->bestellungKennzeichen->delete([['orderId' => $orderId, 'tagId' => $weg]], $context);
        $this->bestellungen->update([[
            'id' => $orderId,
            'customFields' => [Bearbeitung::FELD => $stand],
            'tags' => [$hin],
        ]], $context);
        $this->logger->info('Ketteler-Widerruf: ' . ($daten['bearbeitet'] ? 'als bearbeitet markiert.' : 'Markierung „bearbeitet“ zurückgenommen.'), [
            'bestellung' => $bestellung->getOrderNumber(),
            'benutzer' => $name,
        ]);

        return new JsonResponse($stand);
    }

    /** @return array{0: string, 1: string|null} */
    private function bearbeiter(Context $context): array
    {
        $quelle = $context->getSource();
        $id = $quelle instanceof AdminApiSource ? $quelle->getUserId() : null;
        if ($id === null) {
            return [$quelle instanceof AdminApiSource && $quelle->getIntegrationId() ? 'Integration' : 'System', null];
        }
        $benutzer = $this->benutzer->search(new Criteria([$id]), $context)->first();
        if (!$benutzer instanceof UserEntity) {
            return ['unbekannt', $id];
        }
        $name = trim($benutzer->getFirstName() . ' ' . $benutzer->getLastName());

        return [$name !== '' ? $name . ' (' . $benutzer->getUsername() . ')' : $benutzer->getUsername(), $id];
    }
}
