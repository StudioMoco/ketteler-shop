<?php declare(strict_types=1);

namespace Ketteler\Steuern\Steuern;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Ändert jemand den OSS-Schalter in der Verwaltung, werden die Länderregeln sofort angepasst. */
class KonfigSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Steuerregeln $regeln)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [SystemConfigChangedEvent::class => 'geaendert'];
    }

    public function geaendert(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() === Steuerregeln::KONFIG_OSS) {
            $this->regeln->anwenden(Context::createDefaultContext());
        }
    }
}
