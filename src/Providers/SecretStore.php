<?php
/**
 * Coffre des secrets des réseaux sociaux : option chiffrée + repli .env.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stockage des secrets (tokens, client secrets) saisis dans l'UI d'administration.
 *
 * - Persistance : option `aix_live_secrets` (autoload off), chaque valeur chiffrée
 *   avec sodium (clé secrètebox dérivée des salts de l'installation — la clé ne
 *   vit pas en base, un dump SQL seul ne révèle donc aucun token) ;
 * - Lecture : base chiffrée d'abord, repli `.env` (via {@see Secrets}) pour le
 *   développement local — même ordre UI → .env que la configuration non secrète ;
 * - Les valeurs en clair ne sortent jamais d'ici : ni log, ni HTML, ni exception.
 */
final class SecretStore {

    /** Clé d'option WordPress regroupant les secrets chiffrés. */
    public const OPTION_KEY = 'aix_live_secrets';

    /**
     * True si l'extension sodium est disponible (chiffrement opérationnel).
     */
    public static function available(): bool {
        return function_exists('sodium_crypto_secretbox');
    }

    /**
     * Lit un secret : base chiffrée d'abord, repli .env.
     */
    public static function get(string $providerKey, string $suffix): string {
        $option = get_option(self::OPTION_KEY);
        $blob = is_array($option) ? ($option[$providerKey][$suffix] ?? '') : '';
        if (is_string($blob) && $blob !== '') {
            $plain = self::decrypt($blob);
            if ($plain !== '') {
                return $plain;
            }
        }

        return Secrets::forNetwork($providerKey, $suffix);
    }

    /**
     * True si un secret est disponible (base chiffrée ou .env).
     */
    public static function has(string $providerKey, string $suffix): bool {
        return self::get($providerKey, $suffix) !== '';
    }

    /**
     * Enregistre (ou remplace) un secret, chiffré.
     */
    public static function set(string $providerKey, string $suffix, string $plain): bool {
        if (!self::available() || $plain === '') {
            return false;
        }

        $option = get_option(self::OPTION_KEY);
        $option = is_array($option) ? $option : [];
        if (!isset($option[$providerKey]) || !is_array($option[$providerKey])) {
            $option[$providerKey] = [];
        }
        $option[$providerKey][$suffix] = self::encrypt($plain);

        return update_option(self::OPTION_KEY, $option, false);
    }

    /**
     * Supprime un secret du coffre (le repli .env redevient effectif).
     */
    public static function remove(string $providerKey, string $suffix): void {
        $option = get_option(self::OPTION_KEY);
        if (is_array($option) && isset($option[$providerKey][$suffix])) {
            unset($option[$providerKey][$suffix]);
            update_option(self::OPTION_KEY, $option, false);
        }
    }

    /**
     * Nom de variable .env de repli, pour l'affichage dans l'UI.
     */
    public static function envName(string $providerKey, string $suffix): string {
        return Secrets::envName($providerKey, $suffix);
    }

    /**
     * Clé de chiffrement 32 octets, dérivée des salts de l'installation.
     * Jamais persistée : indisponible pour quiconque ne possède pas wp-config.php.
     */
    private static function key(): string {
        return hash('sha256', wp_salt('auth') . '|' . wp_salt('logged_in'), true);
    }

    private static function encrypt(string $plain): string {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    private static function decrypt(string $blob): string {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());

        return is_string($plain) ? $plain : '';
    }
}
