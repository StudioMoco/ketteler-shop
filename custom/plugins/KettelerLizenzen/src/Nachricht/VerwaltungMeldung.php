<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** Mail an die Verwaltung (KETTELER_VERWALTUNG_EMAIL). Lokal landet sie in Mailpit. */
class VerwaltungMeldung implements Meldung
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function melden(Position $position, string $anlass, string $text): void
    {
        $an = (string) ($_SERVER['KETTELER_VERWALTUNG_EMAIL'] ?? getenv('KETTELER_VERWALTUNG_EMAIL') ?: '');
        if ($an === '') {
            $this->logger->error('Ketteler-Lizenz: keine Adresse für Meldungen (KETTELER_VERWALTUNG_EMAIL).', ['anlass' => $anlass]);

            return;
        }
        $von = (string) ($this->config->get('core.basicInformation.email') ?: 'shop@ketteler-verlag.test');
        $mail = (new Email())
            ->from($von)
            ->to($an)
            ->subject(\sprintf('Shop: %s (Bestellung %s)', $anlass, $position->bestellnummer))
            ->text(implode("\n", [
                'Bitte prüfen und in „Kunden & Lizenzen“ von Hand erledigen.',
                '',
                'Anlass: ' . $anlass,
                'Bestellung: ' . $position->bestellnummer . ' vom ' . $position->bestelldatum,
                'Kunde: ' . $position->name . ' <' . $position->email . '>',
                'Online-Werk: ' . $position->produkt,
                'Menge: ' . $position->menge,
                '',
                'Meldung: ' . $text,
            ]));
        try {
            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            $this->logger->error('Ketteler-Lizenz: Meldung an die Verwaltung nicht versendet: ' . $e->getMessage());
        }
    }
}
