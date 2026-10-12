<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Command;

use Ketteler\Lizenzen\Nachricht\DalPositionenSpeicher;
use Ketteler\Lizenzen\Nachricht\LizenzEreignis;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Lizenz-Ereignisse einer Bestellung erneut in die Warteschlange legen, z. B. nachdem die Verwaltung einen
 * abgelehnten Fall geklärt hat. Dieselbe ereignis_id: die Schnittstelle legt nichts doppelt an.
 */
#[AsCommand(name: 'ketteler:lizenz:erneut', description: 'Lizenz-Ereignisse einer Bestellung erneut senden')]
class ErneutSendenCommand extends Command
{
    public function __construct(
        private readonly EntityRepository $bestellungen,
        private readonly DalPositionenSpeicher $positionen,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('bestellnummer', InputArgument::REQUIRED)
            ->addOption('stornieren', null, InputOption::VALUE_NONE, 'Storno statt Anlage senden')
            ->addOption('erzwingen', null, InputOption::VALUE_NONE, 'auch senden, wenn die Position schon als angelegt gilt (Stand wird auf offen gesetzt)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderNumber', $input->getArgument('bestellnummer')))->addAssociation('lineItems');
        $bestellung = $this->bestellungen->search($criteria, Context::createDefaultContext())->first();
        if (!$bestellung instanceof OrderEntity) {
            $output->writeln('<error>Bestellung nicht gefunden.</error>');

            return self::FAILURE;
        }
        $art = $input->getOption('stornieren') ? LizenzEreignis::STORNIEREN : LizenzEreignis::ANLEGEN;
        $anzahl = 0;
        foreach ($bestellung->getLineItems() ?? [] as $position) {
            if (DalPositionenSpeicher::werk($position) === null) {
                continue;
            }
            if ($input->getOption('erzwingen') && $art === LizenzEreignis::ANLEGEN) {
                $this->positionen->speichern($position->getId(), ['status' => 'offen']);
            }
            $this->bus->dispatch(new LizenzEreignis($bestellung->getId(), $position->getId(), $art));
            ++$anzahl;
        }
        $output->writeln(\sprintf('%d Ereignis(se) in die Warteschlange gelegt.', $anzahl));

        return self::SUCCESS;
    }
}
