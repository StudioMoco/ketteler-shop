<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Anfrage;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AnfrageEntity extends Entity
{
    use EntityIdTrait;

    protected string $einrichtung;

    protected string $ansprechperson;

    protected string $email;

    protected ?string $telefon = null;

    protected int $anzahl;

    protected ?string $nachricht = null;

    protected ?string $artikel = null;

    protected ?string $werk = null;

    protected ?string $salesChannelId = null;
}
