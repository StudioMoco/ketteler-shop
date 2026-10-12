<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Subscriber;

use Ketteler\Lizenzen\Nachricht\LizenzEreignis;
use Ketteler\Lizenzen\Nachricht\Meldung;
use Ketteler\Lizenzen\Nachricht\PositionenSpeicher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/** Nach dem letzten Versuch (≈ 24 Stunden): Stand „fehler“ und Meldung an die Verwaltung. */
class EndgueltigGescheitertSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PositionenSpeicher $positionen,
        private readonly Meldung $meldung,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'gescheitert'];
    }

    public function gescheitert(WorkerMessageFailedEvent $event): void
    {
        $ereignis = $event->getEnvelope()->getMessage();
        if (!$ereignis instanceof LizenzEreignis || $event->willRetry()) {
            return;
        }
        $position = $this->positionen->laden($ereignis->orderId, $ereignis->positionId);
        if ($position === null) {
            return;
        }
        $this->positionen->speichern($ereignis->positionId, $ereignis->art === LizenzEreignis::ANLEGEN ? ['status' => 'fehler'] : ['storno' => 'fehler']);
        $this->meldung->melden(
            $position,
            $ereignis->art === LizenzEreignis::ANLEGEN ? 'Lizenz nicht angelegt' : 'Storno nicht übertragen',
            'Die Lizenz-Schnittstelle war nach allen Wiederholungen nicht erreichbar: ' . $event->getThrowable()->getMessage(),
        );
    }
}
