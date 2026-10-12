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

    /**
     * Alle Online-Zugänge aus Bestellungen des Kunden (auch stornierte), neueste zuerst.
     *
     * @return list<array{werk: string, status: string, bestellnummer: string, datum: string, abo: bool}>
     */
    public function zugaenge(string $kundeId): array
    {
        $zeilen = $this->db->fetchAllAssociative(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(oli.custom_fields, \'$.ketteler_lizenz.werk\')) AS werk,
                    JSON_UNQUOTE(JSON_EXTRACT(oli.custom_fields, \'$.ketteler_lizenz.status\')) AS status,
                    JSON_EXTRACT(oli.payload, \'$.customFields.ketteler_abo\') AS abo,
                    o.order_number AS bestellnummer, o.order_date_time AS datum
               FROM order_line_item oli
               JOIN `order` o ON o.id = oli.order_id AND o.version_id = oli.order_version_id
               JOIN order_customer oc ON oc.order_id = oli.order_id AND oc.order_version_id = oli.order_version_id
              WHERE oc.customer_id = :kunde AND oli.version_id = :live
                AND JSON_EXTRACT(oli.custom_fields, \'$.ketteler_lizenz.status\') IS NOT NULL
              ORDER BY o.order_date_time DESC',
            ['kunde' => Uuid::fromHexToBytes($kundeId), 'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        return array_values(array_map(static fn (array $z): array => [
            'werk' => (string) $z['werk'],
            'status' => (string) $z['status'],
            'bestellnummer' => (string) $z['bestellnummer'],
            'datum' => (string) $z['datum'],
            'abo' => $z['abo'] !== null && $z['abo'] !== 'null' && $z['abo'] !== '""',
        ], array_filter($zeilen, static fn (array $z): bool => \is_string($z['werk']) && $z['werk'] !== '' && $z['werk'] !== 'null')));
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
