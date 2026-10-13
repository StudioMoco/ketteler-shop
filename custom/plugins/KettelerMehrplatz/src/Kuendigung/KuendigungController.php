<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Kuendigung;

use Ketteler\Mehrplatz\Mail\VorlagenMail;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

/**
 * „Verträge hier kündigen“ (Kündigungsschaltfläche nach § 312k BGB): Formular, Bestätigungsseite zum Speichern oder
 * Drucken, Eingangsbestätigung per Mail an die Kundin (mit Datum und Uhrzeit des Eingangs) und Mail an den Kundenservice
 * (KETTELER_KONTAKT_EMAIL). Ohne Anmeldung nutzbar.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class KuendigungController extends StorefrontController
{
    public function __construct(
        private readonly VorlagenMail $vorlagenMail,
        private readonly MailerInterface $mailer,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/vertraege-kuendigen', name: 'frontend.ketteler.kuendigen', defaults: ['_noStore' => true], methods: ['GET'])]
    public function formular(SalesChannelContext $context): Response
    {
        $kunde = $context->getCustomer();

        return $this->seite([
            'vorname' => $kunde?->getFirstName() ?? '', 'nachname' => $kunde?->getLastName() ?? '', 'email' => $kunde?->getEmail() ?? '',
            'email2' => $kunde?->getEmail() ?? '', 'art' => 'ordentlich', 'termin' => 'naechstmoeglich',
        ], [], null);
    }

    #[Route(path: '/vertraege-kuendigen', name: 'frontend.ketteler.kuendigen.senden', defaults: ['_captcha' => true], methods: ['POST'])]
    public function senden(Request $request, SalesChannelContext $context): Response
    {
        [$daten, $fehler] = Pruefung::pruefe($request->request->all());
        if ($fehler !== []) {
            return $this->seite($request->request->all(), $fehler, null);
        }
        $eingang = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('d.m.Y, H:i') . ' Uhr';
        $daten['eingang'] = $eingang;
        $this->anKundenservice($daten);
        $this->vorlagenMail->senden('ketteler_kuendigung_bestaetigung', (string) $daten['email'], $daten['vorname'] . ' ' . $daten['nachname'], $daten, $context);
        $this->logger->info('Ketteler-Kündigung: eingegangen.', ['vertrag' => $daten['vertrag']]);

        return $this->seite($daten, [], $eingang);
    }

    /**
     * @param array<string, mixed> $daten
     * @param array<string, string> $fehler
     */
    private function seite(array $daten, array $fehler, ?string $eingang): Response
    {
        return $this->renderStorefront('@KettelerMehrplatz/storefront/page/ketteler/kuendigen.html.twig', [
            'daten' => $daten, 'fehler' => $fehler, 'eingang' => $eingang,
        ]);
    }

    /** @param array<string, mixed> $daten */
    private function anKundenservice(array $daten): void
    {
        $an = (string) ($_SERVER['KETTELER_KONTAKT_EMAIL'] ?? getenv('KETTELER_KONTAKT_EMAIL') ?: '');
        if ($an === '') {
            $this->logger->error('Ketteler-Kündigung: keine Empfängeradresse (KETTELER_KONTAKT_EMAIL).');

            return;
        }
        $zeilen = [
            'Neue Kündigung über „Verträge hier kündigen“ im Shop, eingegangen am ' . $daten['eingang'] . '.',
            '',
            'Abo-Nummer oder letzte Rechnungsnummer: ' . $daten['vertrag'],
            'Name: ' . $daten['vorname'] . ' ' . $daten['nachname'],
            'Firma: ' . $daten['firma'],
            'Anschrift: ' . trim($daten['strasse'] . ', ' . $daten['plz'] . ' ' . $daten['ort'], ', '),
            'E-Mail: ' . $daten['email'],
            'Art: ' . ($daten['art'] === 'ausserordentlich' ? 'Außerordentliche Kündigung' : 'Ordentliche Kündigung'),
            'Grund: ' . $daten['grund'],
            'Zeitpunkt: ' . ($daten['termin'] === 'wunschtermin' ? 'zum Wunschtermin ' . $daten['datum'] : 'zum nächstmöglichen Zeitpunkt'),
            '',
            'Die Kundin bzw. der Kunde hat eine Eingangsbestätigung per Mail erhalten. Bitte den Vertrag in weclapp beenden und die',
            'Kündigung mit dem Datum des Vertragsendes bestätigen.',
        ];
        $mail = (new Email())
            ->from((string) ($this->config->get('core.basicInformation.email') ?: 'kontakt@ketteler-verlag.de'))
            ->to($an)
            ->replyTo(new Address((string) $daten['email'], $daten['vorname'] . ' ' . $daten['nachname']))
            ->subject('Kündigung: ' . $daten['vertrag'] . ' (' . $daten['vorname'] . ' ' . $daten['nachname'] . ')')
            ->text(implode("\n", $zeilen));
        try {
            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            $this->logger->error('Ketteler-Kündigung: Mail an den Kundenservice nicht versendet: ' . $e->getMessage());
        }
    }
}
