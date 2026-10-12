<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Adressen kommen aus der Umgebung (.env), Client-ID und Kunden-Organisation aus der Plugin-Konfiguration.
 * KETTELER_ZITADEL_URL ist die Adresse für den Browser (= Aussteller im Token). KETTELER_ZITADEL_INTERN ist
 * optional die Adresse, unter der der Shop-Server ZITADEL erreicht (lokal http://zitadel:8080).
 */
class Konfiguration
{
    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function aussteller(): string
    {
        return rtrim(self::env('KETTELER_ZITADEL_URL'), '/');
    }

    public function intern(): string
    {
        return rtrim(self::env('KETTELER_ZITADEL_INTERN') ?: self::env('KETTELER_ZITADEL_URL'), '/');
    }

    public function clientId(?string $salesChannelId = null): string
    {
        return (string) $this->config->get('KettelerAnmeldung.config.clientId', $salesChannelId);
    }

    public function organisationId(?string $salesChannelId = null): string
    {
        return (string) $this->config->get('KettelerAnmeldung.config.organisationId', $salesChannelId);
    }

    public function eingerichtet(?string $salesChannelId = null): bool
    {
        return $this->aussteller() !== '' && $this->clientId($salesChannelId) !== '';
    }

    public static function env(string $name): string
    {
        $wert = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($wert) ? trim($wert) : '';
    }
}
