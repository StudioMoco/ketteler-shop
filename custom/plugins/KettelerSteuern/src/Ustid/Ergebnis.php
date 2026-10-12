<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

/** Ergebnis der Prüfung einer USt-IdNr. */
final class Ergebnis
{
    public const GUELTIG = 'gueltig';
    public const UNGUELTIG = 'ungueltig';
    public const NICHT_PRUEFBAR = 'nicht_pruefbar';
    public const INLAND = 'inland';
    public const FORMAT = 'format';

    public function __construct(
        public readonly string $status,
        public readonly string $ustid,
        public readonly ?string $iso,
        public readonly string $geprueft,
        public readonly ?string $name = null,
        public readonly ?string $fehler = null,
    ) {
    }

    /** @return array<string, string|null> für das Zusatzfeld ketteler_ustid am Kunden */
    public function alsFeld(): array
    {
        return ['status' => $this->status, 'ustid' => $this->ustid, 'land' => $this->iso, 'geprueft' => $this->geprueft, 'name' => $this->name, 'fehler' => $this->fehler];
    }
}
