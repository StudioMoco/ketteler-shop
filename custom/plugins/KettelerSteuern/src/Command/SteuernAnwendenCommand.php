<?php declare(strict_types=1);

namespace Ketteler\Steuern\Command;

use Ketteler\Steuern\Steuern\Steuerregeln;
use Ketteler\Steuern\Ustid\UstIdPruefung;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Länderregeln nach dem OSS-Schalter setzen; mit --ustid alle gespeicherten USt-IdNr. neu prüfen (VIES). */
#[AsCommand(name: 'ketteler:steuern:anwenden', description: 'Steuerregeln nach dem OSS-Schalter setzen, optional USt-IdNr. neu prüfen')]
class SteuernAnwendenCommand extends Command
{
    public function __construct(
        private readonly Steuerregeln $regeln,
        private readonly UstIdPruefung $pruefung,
        private readonly EntityRepository $kunden,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('ustid', null, InputOption::VALUE_NONE, 'alle gespeicherten USt-IdNr. neu über VIES prüfen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();
        $ergebnis = $this->regeln->anwenden($context);
        $output->writeln($ergebnis['oss']
            ? \sprintf('OSS an: %d Länderregeln gesetzt (Sätze sind Platzhalter).', $ergebnis['regeln'])
            : 'OSS aus: keine Länderregeln, deutsche Umsatzsteuer für Privatkunden in der EU.');
        if ($input->getOption('ustid')) {
            $ids = $this->kunden->searchIds((new Criteria())->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('vatIds', null)])), $context)->getIds();
            foreach ($ids as $id) {
                $e = $this->pruefung->pruefeKunde((string) $id, $context);
                $output->writeln(\sprintf('  %s: %s', $e?->ustid ?? '-', $e?->status ?? 'keine'));
            }
        }

        return self::SUCCESS;
    }
}
