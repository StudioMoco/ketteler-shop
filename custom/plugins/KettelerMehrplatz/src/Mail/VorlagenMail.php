<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Mail;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Mail an Kundinnen und Kunden über eine Mailvorlage von Shopware (Inhalte > E-Mail-Vorlagen), mit Kopf und Fuß des
 * Verkaufskanals wie alle anderen Mails. Die Vorlagen legt ketteler:einrichten an (daten/mails/<technischer Name>).
 */
class VorlagenMail
{
    public function __construct(
        private readonly AbstractMailService $mails,
        private readonly EntityRepository $vorlagen,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $daten Daten für die Vorlage (dort unter „daten“) */
    public function senden(string $typ, string $email, string $name, array $daten, SalesChannelContext $context): bool
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('mailTemplateType.technicalName', $typ))->setLimit(1);
        $vorlage = $this->vorlagen->search($criteria, $context->getContext())->first();
        if ($vorlage === null) {
            $this->logger->error('Ketteler: Mailvorlage ' . $typ . ' fehlt (ketteler:einrichten).');

            return false;
        }
        try {
            $this->mails->send([
                'recipients' => [$email => $name !== '' ? $name : $email],
                'senderName' => $vorlage->getTranslation('senderName') ?: 'KETTELER-Verlag',
                'salesChannelId' => $context->getSalesChannelId(),
                'subject' => (string) $vorlage->getTranslation('subject'),
                'contentHtml' => (string) $vorlage->getTranslation('contentHtml'),
                'contentPlain' => (string) $vorlage->getTranslation('contentPlain'),
            ], $context->getContext(), ['daten' => $daten]);
        } catch (\Throwable $e) {
            $this->logger->error('Ketteler: Mail ' . $typ . ' nicht versendet: ' . $e->getMessage());

            return false;
        }

        return true;
    }
}
