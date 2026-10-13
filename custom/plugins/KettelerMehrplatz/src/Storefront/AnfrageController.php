<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Storefront;

use Ketteler\Mehrplatz\Anfrage\Pruefung;
use Ketteler\Mehrplatz\Mail\VorlagenMail;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

/** „Mehrere Lizenzen anfragen“: Formular, Speichern, Mail an den Kundenservice und Eingangsbestätigung an die Kundin. */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class AnfrageController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $anfragen,
        private readonly VorlagenMail $vorlagenMail,
        private readonly MailerInterface $mailer,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mehrplatz-anfrage', name: 'frontend.ketteler.mehrplatz', defaults: ['_noStore' => true], methods: ['GET'])]
    public function formular(Request $request, SalesChannelContext $context): Response
    {
        return $this->seite([
            'artikel' => (string) $request->query->get('artikel', ''),
            'werk' => (string) $request->query->get('werk', ''),
            'einrichtung' => $context->getCustomer()?->getCompany() ?? '',
            'ansprechperson' => $context->getCustomer() ? trim($context->getCustomer()->getFirstName() . ' ' . $context->getCustomer()->getLastName()) : '',
            'email' => $context->getCustomer()?->getEmail() ?? '',
        ], []);
    }

    #[Route(path: '/mehrplatz-anfrage', name: 'frontend.ketteler.mehrplatz.senden', defaults: ['_captcha' => true], methods: ['POST'])]
    public function senden(Request $request, SalesChannelContext $context): Response
    {
        [$daten, $fehler] = Pruefung::pruefe($request->request->all());
        if ($fehler !== []) {
            return $this->seite($daten, $fehler);
        }

        $id = Uuid::randomHex();
        $this->anfragen->create([$daten + ['id' => $id, 'salesChannelId' => $context->getSalesChannelId()]], $context->getContext());
        $this->mailSenden($daten);
        // Eingangsbestätigung an die anfragende Person (Mailvorlage ketteler_mehrplatz_bestaetigung)
        $this->vorlagenMail->senden('ketteler_mehrplatz_bestaetigung', (string) $daten['email'], (string) $daten['ansprechperson'], $daten, $context);
        $this->logger->info('Ketteler-Mehrplatz: Anfrage gespeichert.', ['anfrage' => $id]);

        return $this->renderStorefront('@KettelerMehrplatz/storefront/page/ketteler/mehrplatz.html.twig', ['gesendet' => true, 'daten' => $daten]);
    }

    /**
     * @param array<string, mixed> $daten
     * @param array<string, string> $fehler
     */
    private function seite(array $daten, array $fehler): Response
    {
        return $this->renderStorefront('@KettelerMehrplatz/storefront/page/ketteler/mehrplatz.html.twig', ['gesendet' => false, 'daten' => $daten, 'fehler' => $fehler]);
    }

    /** @param array<string, mixed> $daten */
    private function mailSenden(array $daten): void
    {
        $an = (string) ($_SERVER['KETTELER_KONTAKT_EMAIL'] ?? getenv('KETTELER_KONTAKT_EMAIL') ?: '');
        if ($an === '') {
            $this->logger->error('Ketteler-Mehrplatz: keine Empfängeradresse (KETTELER_KONTAKT_EMAIL), Anfrage nur gespeichert.');

            return;
        }
        $zeilen = [
            'Neue Anfrage für mehrere Lizenzen aus dem Shop.',
            '',
            'Einrichtung: ' . $daten['einrichtung'],
            'Ansprechperson: ' . $daten['ansprechperson'],
            'E-Mail: ' . $daten['email'],
            'Telefon: ' . ($daten['telefon'] ?? ''),
            'Anzahl der Lizenzen: ' . $daten['anzahl'],
            'Werk: ' . ($daten['werk'] ?? ''),
            'Artikel: ' . ($daten['artikel'] ?? ''),
            '',
            'Nachricht:',
            (string) ($daten['nachricht'] ?? ''),
            '',
            'Die Anfrage steht auch in der Shopware-Verwaltung unter Kunden > Mehrplatz-Anfragen.',
        ];
        $mail = (new Email())
            ->from((string) ($this->config->get('core.basicInformation.email') ?: 'shop@ketteler-verlag.test'))
            ->to($an)
            ->replyTo(new Address((string) $daten['email'], (string) $daten['ansprechperson']))
            ->subject(\sprintf('Anfrage mehrere Lizenzen: %s (%d)', $daten['einrichtung'], $daten['anzahl']))
            ->text(implode("\n", $zeilen));
        try {
            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            $this->logger->error('Ketteler-Mehrplatz: Mail nicht versendet: ' . $e->getMessage());
        }
    }
}
