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
 * - Persistance : option `aix_live_secrets` (autoload off), chaque valeur
 *   chiffrée avec sodium (clé secrètebox dérivée des salts de l'installation —
 *   la clé ne vit pas en base, un dump SQL seul ne révèle donc aucun token) ;
 * - Chaque blob embarque un condensat de contrôle de la clé (audit SEC-08) :
 *   si les salts de l'installation changent, la lecture échoue silencieusement
 *   (repli .env) au lieu de lever des erreurs sodium, et {@see self::status()}
 *   permet à l'UI d'afficher l'alerte « ressaisir les secrets ». Format
   historique (≤ 2.16.0, blob brut sans condensat) toujours lisible ;
 * - Lecture : base chiffrée d'abord, repli `.env` (via {@see Secrets}) pour le
 *   développement local — même ordre UI → .env que la configuration non secrète ;
 * - Les valeurs en clair ne sortent jamais d'ici : ni log, ni HTML, ni exception.
 */
final class SecretStore {

    /** Clé d'option WordPress regroupant les secrets chiffrés. */
    public const OPTION_KEY = 'aix_live_secrets';

    /** Longueur du condensat de contrôle de la clé, en caractères hexadécimaux. */
    private const FINGERPRINT_LENGTH = 16;

    /**
     * True si l'extension sodium est disponible (chiffrement opérationnel).
     */
    public static function available(): bool {
        return function_exists('sodium_crypto_secretbox');
    }

    /**
     * État du coffre vis-à-vis de la clé courante :
     * - 'empty'   : aucun secret chiffré en base ;
     * - 'unknown' : uniquement des blobs au format historique (sans condensat) ;
     * - 'ok'      : blobs au format nouveau, tous déchiffrables ;
     * - 'rotated' : au moins un blob illisible — les salts ont changé depuis
     *   le chiffrement, les secrets concernés doivent être ressaisis.
     */
    public static function status(): string {
        $option = get_option(self::OPTION_KEY);
        if (!is_array($option)) {
            return 'empty';
        }

        $state = 'empty';
        foreach ($option as $entries) {
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if (is_array($entry)) {
                    if (!hash_equals((string) ($entry['key'] ?? ''), self::keyFingerprint())) {
                        return 'rotated';
                    }
                    $state = 'ok';
                } elseif (is_string($entry) && $entry !== '' && $state === 'empty') {
                    $state = 'unknown';
                }
            }
        }

        return $state;
    }

    /**
     * Lit un secret : base chiffrée d'abord, repli .env.
     */
    public static function get(string $providerKey, string $suffix): string {
        $option = get_option(self::OPTION_KEY);
        $entry = is_array($option) ? ($option[$providerKey][$suffix] ?? '') : '';
        if (is_array($entry)) {
            if (hash_equals((string) ($entry['key'] ?? ''), self::keyFingerprint())) {
                $plain = self::decrypt((string) ($entry['blob'] ?? ''));
                if ($plain !== '') {
                    return $plain;
                }
            }
        } elseif (is_string($entry) && $entry !== '') {
            // Format historique : blob brut sans condensat de contrôle.
            $plain = self::decrypt($entry);
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
     * Enregistre (ou remplace) un secret, chiffré avec la clé courante.
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
        $option[$providerKey][$suffix] = [
            'blob' => self::encrypt($plain),
            'key'  => self::keyFingerprint(),
        ];

        return update_option(self::OPTION_KEY, $option, false);
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

    /**
     * Condensat de contrôle de la clé, stocké à côté de chaque blob (jamais la
     * clé elle-même) : permet de détecter une rotation des salts (audit SEC-08).
     */
    private static function keyFingerprint(): string {
        return substr(hash('sha256', self::key()), 0, self::FINGERPRINT_LENGTH);
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
