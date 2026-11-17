<?php
/**
 * Configuration reCAPTCHA v3 : clés, seuil de score, activation par formulaire.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Recaptcha;

use AinatrixLive\Providers\Secrets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Résolution de la configuration reCAPTCHA v3. Les clés vivent dans le .env
 * (AIX_LIVE_RECAPTCHA_SITE_KEY / AIX_LIVE_RECAPTCHA_SECRET_KEY, voir
 * .env.example) et sont filtrables pour un déploiement par mu-plugin ou
 * constante serveur. Aucun secret reCAPTCHA n'est stocké en base : hors
 * périmètre de SecretStore, lecture uniquement via Secrets (.env + filtres).
 */
final class RecaptchaConfig {

    /** Identifiant du formulaire de connexion. */
    public const FORM_LOGIN = 'login';

    /** Identifiant du formulaire de réglages admin. */
    public const FORM_SETTINGS = 'settings';

    /** Bornes du seuil de score, pour éviter un réglage aberrant via filtre. */
    private const THRESHOLD_MIN = 0.1;
    private const THRESHOLD_MAX = 0.9;

    /**
     * Clé de site (publique : c'est elle qui est injectée dans les pages).
     */
    public function siteKey(): string {
        return (string) apply_filters('aix_live_recaptcha_site_key', Secrets::get('AIX_LIVE_RECAPTCHA_SITE_KEY'));
    }

    /**
     * Clé secrète (jamais exposée côté client, jamais loggée).
     */
    public function secretKey(): string {
        return (string) apply_filters('aix_live_recaptcha_secret_key', Secrets::get('AIX_LIVE_RECAPTCHA_SECRET_KEY'));
    }

    /**
     * Score minimal requis, borné [0.1, 0.9] (défaut 0.5).
     */
    public function threshold(): float {
        $threshold = (float) apply_filters('aix_live_recaptcha_threshold', 0.5);

        return max(self::THRESHOLD_MIN, min(self::THRESHOLD_MAX, $threshold));
    }

    /**
     * Captcha actif pour un formulaire donné. Défauts : connexion si les clés
     * sont présentes, réglages admin désactivé (opt-in via filtre
     * aix_live_recaptcha_enabled_settings).
     */
    public function isEnabledFor(string $form): bool {
        $default = $form === self::FORM_LOGIN ? $this->isConfigured() : false;

        return (bool) apply_filters('aix_live_recaptcha_enabled_' . $form, $default);
    }

    /**
     * Les deux clés sont présentes et non vides.
     */
    public function isConfigured(): bool {
        return $this->siteKey() !== '' && $this->secretKey() !== '';
    }
}
