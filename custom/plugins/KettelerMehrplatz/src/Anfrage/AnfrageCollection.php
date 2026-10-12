<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Anfrage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<AnfrageEntity> */
class AnfrageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AnfrageEntity::class;
    }
}
