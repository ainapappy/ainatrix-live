<?php
/**
 * Sanitization et enregistrement des réglages des réseaux sociaux.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\SocialSettings;

use AinatrixLive\Providers\ProviderConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nettoie la soumission du formulaire (tableau aix_live[réseau][champ]) selon
 * le schéma : liste blanche stricte — un champ absent du schéma n'est jamais
 * persisté, et les champs .env n'existent pas côté POST.
 */
final class SettingsStore {

    /** Bornes du délai d'expiration HTTP, en secondes. */
    private const TIMEOUT_MIN = 5;
    private const TIMEOUT_MAX = 120;

    /**
     * Nettoie la soumission complète et retourne l'option prête à persister.
     *
     * @param array<string, mixed> $raw Tableau brut aix_live[réseau][champ] (déjà wp_unslash).
     * @return array<string, array<string, mixed>>
     */
    public static function sanitize(array $raw): array {
        $clean = [];
        foreach (SettingsSchema::PAGES as $page) {
            $pageRaw = isset($raw[$page]) && is_array($raw[$page]) ? $raw[$page] : [];
            $clean[$page] = self::sanitizePage($page, $pageRaw);
        }

        return $clean;
    }

    /**
     * Enregistre la soumission (après sanitize par l'appelant) dans l'option.
     *
     * @param array<string, array<string, mixed>> $settings Tableau nettoyé réseau => champs.
     */
    public static function save(array $settings): void {
        update_option(ProviderConfig::OPTION_KEY, $settings, true);
    }

    /**
     * Nettoie les champs d'une page selon leur type déclaré.
     *
     * @param array<string, mixed> $pageRaw
     * @return array<string, mixed>
     */
    private static function sanitizePage(string $page, array $pageRaw): array {
        $clean = [];
        foreach (SettingsSchema::persistedFields($page) as $key => $field) {
            $value = $pageRaw[$key] ?? null;
            $clean[$key] = match ($field['type']) {
                'checkbox' => !empty($value),
                'number'   => self::timeoutValue($value),
                'select'   => self::selectValue($field, $value),
                default    => sanitize_textarea_field((string) $value),
            };
        }

        return $clean;
    }

    /**
     * Valeur numérique bornée (champ timeout).
     */
    private static function timeoutValue(mixed $value): int {
        return max(self::TIMEOUT_MIN, min(self::TIMEOUT_MAX, absint($value)));
    }

    /**
     * Valeur d'une liste déroulante : acceptée uniquement si dans la liste blanche.
     *
     * @param array<string, mixed> $field
     */
    private static function selectValue(array $field, mixed $value): string {
        $value = (string) $value;

        return array_key_exists($value, $field['options']) ? $value : (string) $field['default'];
    }
}
