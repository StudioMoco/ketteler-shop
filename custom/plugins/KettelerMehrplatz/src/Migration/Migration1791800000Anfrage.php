<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1791800000Anfrage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791800000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `ketteler_mehrplatz_anfrage` (
                `id` BINARY(16) NOT NULL,
                `einrichtung` VARCHAR(255) NOT NULL,
                `ansprechperson` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `telefon` VARCHAR(100) NULL,
                `anzahl` INT NOT NULL,
                `nachricht` LONGTEXT NULL,
                `artikel` VARCHAR(255) NULL,
                `werk` VARCHAR(50) NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.ketteler_mehrplatz.created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
