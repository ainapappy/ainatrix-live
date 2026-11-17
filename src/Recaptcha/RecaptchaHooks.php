<?php
/**
 * Classe de hooks de la fonctionnalité reCAPTCHA v3.
 *
 * Modèle d'application (ordres d'exécution documentés car hypothèses
 * fragiles — toute re-priorisation du plugin doit relire ceci) :
 *
 * - wp-login.php : le filtre `wp_authenticate_user` se déclenche une fois le
 *   compte résolu, AVANT la vérification du mot de passe. À priorité 20, ce
 *   contrôle captcha passe APRÈS le verrou rate-limit de LoginExperienceHooks
 *   (priorité 10 — on ne challenge pas un IP/identifiant déjà verrouillé) et
 *   AVANT la vérification du mot de passe : un WP_Error retourné ici est
 *   définitif, le cœur ne peut pas l'écraser.
 *
 * - admin-post aix_live_save_settings : les callbacks de l'action
 *   `admin_post_{action}` s'exécutent par priorité croissante ; le contrôle
 *   captcha à priorité 9 précède handleSave de SocialSettingsHooks (priorité
 *   par défaut 10, qui re-relit de toute façon capability + nonce).
 *
 * Sans clés dans le .env (ni filtres), toute la surface est un no-op complet :
 * ni champ rendu, ni script chargé, ni contrôle ajouté.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Recaptcha;

use AinatrixLive\Hooks\HookSubscriberInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class RecaptchaHooks implements HookSubscriberInterface {

    /** Nom du champ caché portant le token (aucun rapport avec le secret). */
    public const TOKEN_FIELD = 'aix_live_recaptcha_token';

    public function __construct(
        private readonly RecaptchaConfig $config = new RecaptchaConfig(),
        private readonly RecaptchaClient $client = new RecaptchaClient(),
    ) {}

    public function register(): void {
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueSettingsAssets']);
        add_action('aix_live_form_recaptcha', [$this, 'renderFormField']);
        add_filter('wp_authenticate_user', [$this, 'enforceLoginToken'], 20, 1);
        // Chaîne littérale = SocialSettingsHooks::SAVE_ACTION (slug 'aix_live_save_settings') :
        // littéral ici pour ne pas coupler les deux fonctionnalités ; à garder en phase.
        add_action('admin_post_aix_live_save_settings', [$this, 'enforceSettingsToken'], 9);
    }

    /**
     * Charge l'API Google + le runner sur wp-login.php, uniquement pour la
     * soumission de connexion standard.
     */
    public function enqueueLoginAssets(): void {
        if (sanitize_key((string) wp_unslash($_REQUEST['action'] ?? 'login')) !== 'login') {
            return;
        }
        if (!$this->config->isEnabledFor(RecaptchaConfig::FORM_LOGIN) || !$this->config->isConfigured()) {
            return;
        }

        $this->enqueueRecaptchaAssets();
    }

    /**
     * Charge l'API Google + le runner sur la page de réglages admin.
     *
     * @param string $hookSuffix Suffixe de hook admin (non utilisé : la détection
     *                          passe par $_GET['page'], alignée sur
     *                          SocialSettingsHooks::enqueueAssets).
     */
    public function enqueueSettingsAssets(string $hookSuffix): void {
        if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== 'ainatrix-social') {
            return;
        }
        if (!$this->config->isEnabledFor(RecaptchaConfig::FORM_SETTINGS) || !$this->config->isConfigured()) {
            return;
        }

        $this->enqueueRecaptchaAssets();
    }

    /**
     * Rend le champ caché du token dans un formulaire marqué. No-op si le
     * captcha est désactivé pour ce formulaire ou les clés absentes. Le secret
     * n'apparaît jamais ici (seule la clé publique de site est rendue).
     *
     * @param string $form Identifiant du formulaire ('login', 'settings').
     */
    public function renderFormField(string $form): void {
        if (!$this->config->isEnabledFor($form) || !$this->config->isConfigured()) {
            return;
        }

        echo '<input type="hidden" name="' . esc_attr(self::TOKEN_FIELD) . '" value=""'
            . ' data-aix-recaptcha-site-key="' . esc_attr($this->config->siteKey()) . '"'
            . ' data-aix-recaptcha-action="' . esc_attr($form) . '">';
    }

    /**
     * Contrôle captcha sur la connexion (filtre wp_authenticate_user,
     * priorité 20 : après le rate-limit p10, avant la vérification du mot de
     * passe — voir en-tête de classe).
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function enforceLoginToken($user) {
        if (!$user instanceof \WP_User || !$this->isStandardLoginPost()) {
            return $user;
        }
        if (!$this->config->isEnabledFor(RecaptchaConfig::FORM_LOGIN) || !$this->config->isConfigured()) {
            return $user;
        }

        $token = isset($_POST[self::TOKEN_FIELD])
            ? sanitize_text_field((string) wp_unslash($_POST[self::TOKEN_FIELD]))
            : '';
        if ($token === '') {
            // Refus sans appel réseau : pas de requête Google payée par un bot.
            return new \WP_Error('aix_live_recaptcha_failed', $this->errorMessage());
        }

        // Le filtre aix_live_recaptcha_verdict et le do_action d'échec sont
        // déjà appliqués dans RecaptchaClient::verify().
        $verdict = $this->client->verify($token, RecaptchaConfig::FORM_LOGIN);
        if (!$verdict->isTrusted($this->config->threshold())) {
            return new \WP_Error('aix_live_recaptcha_failed', $this->errorMessage());
        }

        return $user;
    }

    /**
     * Contrôle captcha sur l'enregistrement des réglages admin (priorité 9,
     * avant handleSave — voir en-tête de classe). No-op si le captcha est
     * désactivé pour ce formulaire : l'enregistrement doit rester possible
     * sans configuration reCAPTCHA.
     */
    public function enforceSettingsToken(): void {
        if (!$this->config->isEnabledFor(RecaptchaConfig::FORM_SETTINGS) || !$this->config->isConfigured()) {
            return;
        }

        // Défense en profondeur : handleSave le revérifie, mais on refuse ici
        // AVANT tout appel réseau vers Google.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ainatrix'));
        }

        $token = isset($_POST[self::TOKEN_FIELD])
            ? sanitize_text_field((string) wp_unslash($_POST[self::TOKEN_FIELD]))
            : '';
        if ($token === '') {
            wp_die(esc_html($this->errorMessage()), ['response' => 400]);
        }

        $verdict = $this->client->verify($token, RecaptchaConfig::FORM_SETTINGS);
        if (!$verdict->isTrusted($this->config->threshold())) {
            wp_die(esc_html($this->errorMessage()), ['response' => 400]);
        }
    }

    /**
     * Dupliquée de LoginExperienceHooks::isStandardLoginPost() (privée là-bas,
     * et on ne couple pas deux fonctionnalités) : trois conditions identiques —
     * action login, méthode POST, champs identifiant + mot de passe présents.
     * À garder en phase si l'une des deux évolue.
     */
    private function isStandardLoginPost(): bool {
        $action = sanitize_key((string) wp_unslash($_REQUEST['action'] ?? 'login'));
        if ($action !== 'login') {
            return false;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }

        return isset($_POST['log'], $_POST['pwd']);
    }

    /**
     * Message générique (anti-fuite) : aucune distinction token absent / score
     * faible / erreur Google. Littéral inline : une constante passée à __()
     * n'est pas extraite par les outils i18n (même modèle que
     * LoginExperienceHooks::errorMessage).
     */
    private function errorMessage(): string {
        return __('Vérification anti-robot échouée. Veuillez recharger la page et réessayer.', 'ainatrix');
    }

    /**
     * Enfile l'API Google (clé de site publique dans l'URL, version null pour
     * éviter un ?ver= qui casserait le cache de l'API) puis le runner du
     * plugin. Commun aux formulaires login et réglages.
     */
    private function enqueueRecaptchaAssets(): void {
        $siteKey = $this->config->siteKey();
        if ($siteKey === '') {
            return;
        }

        $locale = str_replace('_', '-', get_user_locale());
        $apiUrl = esc_url(sprintf(
            'https://www.google.com/recaptcha/api.js?render=%1$s&hl=%2$s',
            rawurlencode($siteKey),
            rawurlencode($locale)
        ));

        wp_enqueue_script('aix-live-recaptcha', $apiUrl, [], null, true);
        wp_enqueue_script(
            'aix-live-recaptcha-run',
            AIX_LIVE_URL . 'assets/js/aix-live-recaptcha.js',
            ['aix-live-recaptcha', 'jquery'],
            AIX_LIVE_VERSION,
            true
        );
    }
}
