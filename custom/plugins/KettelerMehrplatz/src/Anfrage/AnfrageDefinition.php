<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Anfrage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AnfrageDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ketteler_mehrplatz_anfrage';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AnfrageEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AnfrageCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('einrichtung', 'einrichtung'))->addFlags(new ApiAware(), new Required()),
            (new StringField('ansprechperson', 'ansprechperson'))->addFlags(new ApiAware(), new Required()),
            (new StringField('email', 'email'))->addFlags(new ApiAware(), new Required()),
            (new StringField('telefon', 'telefon', 100))->addFlags(new ApiAware()),
            (new IntField('anzahl', 'anzahl', 1))->addFlags(new ApiAware(), new Required()),
            (new LongTextField('nachricht', 'nachricht'))->addFlags(new ApiAware()),
            (new StringField('artikel', 'artikel'))->addFlags(new ApiAware()),
            (new StringField('werk', 'werk', 50))->addFlags(new ApiAware()),
            (new IdField('sales_channel_id', 'salesChannelId'))->addFlags(new ApiAware()),
        ]);
    }
}
