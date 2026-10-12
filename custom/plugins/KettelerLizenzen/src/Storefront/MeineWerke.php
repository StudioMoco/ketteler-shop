<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Storefront;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Online-Werke eines Kunden aus seinen Bestellungen (Stand der Lizenz an der Bestellposition).
 * „bereit“ = Lizenz angelegt; offen/wartet = wird gerade eingerichtet.
 */
class MeineWerke
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, array{werk: string, bereit: bool}> */
    public function fuerKunde(string $kundeId): array
    {
        $zeilen = $this->db->fetchAllAssociative(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(oli.custom_fields, \'$.ketteler_lizenz.werk\')) AS werk,
                    JSON_UNQUOTE(JSON_EXTRACT(oli.custom_fields, \'$.ketteler_lizenz.status\')) AS status
               FROM order_line_item oli
               JOIN order_customer oc ON oc.order_id = oli.order_id AND oc.order_version_id = oli.order_version_id
              WHERE oc.customer_id = :kunde
                AND oli.version_id = :live
                AND JSON_UNQUOTE(JSON_EXTRACT(oli.custom_fields, \'$.ketteler_lizenz.status\')) IN (:stati)
              ORDER BY oli.created_at',
            ['kunde' => Uuid::fromHexToBytes($kundeId), 'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'stati' => ['angelegt', 'offen', 'wartet']],
            ['stati' => ArrayParameterType::STRING],
        );
        $werke = [];
        foreach ($zeilen as $z) {
            if (!\is_string($z['werk']) || $z['werk'] === '' || $z['werk'] === 'null') {
                continue;
            }
            $werke[$z['werk']] = ['werk' => $z['werk'], 'bereit' => ($werke[$z['werk']]['bereit'] ?? false) || $z['status'] === 'angelegt'];
        }

        return $werke;
    }
}
