<?php
/**
 * Point d'accès unique aux secrets du .env du plugin ainatrix live.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lecture des variables d'environnement. Toute la couche Providers passe par
 * cette classe pour accéder au .env — jamais d'appel getenv() direct ailleurs.
 *
 * Convention de nommage : AIX_LIVE_ + préfixe court du réseau + suffixe en
 * SCREAMING_SNAKE_CASE (ex. AIX_LIVE_FB_APP_SECRET). Instagram partage
 * l'application Meta et le page token de Facebook : même préfixe.
 */
final class Secrets {

    /**
     * Préfixe .env par réseau social.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        'facebook'  => 'AIX_LIVE_FB_',
        'instagram' => 'AIX_LIVE_FB_',
        'linkedin'  => 'AIX_LIVE_LINKEDIN_',
        'tiktok'    => 'AIX_LIVE_TIKTOK_',
    ];

    /**
     * Lit une variable du .env, avec repli.
     */
    public static function get(string $key, string $default = ''): string {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }

    /**
     * Vérifie qu'une variable du .env est présente et non vide.
     */
    public static function has(string $key): bool {
        return self::get($key) !== '';
    }

    /**
     * Lit une variable du .env pour un réseau donné.
     *
     * @param string $providerKey Clé du réseau ('facebook', 'linkedin', …).
     * @param string $suffix      Suffixe du nom de variable ('APP_SECRET', 'PAGE_TOKEN', …).
     */
    public static function forNetwork(string $providerKey, string $suffix): string {
        return self::get(self::envName($providerKey, $suffix));
    }

    /**
     * Vérifie qu'une variable de réseau est présente et non vide.
     */
    public static function hasFor(string $providerKey, string $suffix): bool {
        return self::forNetwork($providerKey, $suffix) !== '';
    }

    /**
     * Nom de variable .env complet pour un réseau et un suffixe donnés.
     */
    public static function envName(string $providerKey, string $suffix): string {
        $prefix = self::PREFIXES[$providerKey] ?? 'AIX_LIVE_' . strtoupper($providerKey) . '_';

        return $prefix . strtoupper($suffix);
    }
}
