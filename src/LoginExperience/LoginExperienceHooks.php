<?php
/**
 * Classe de hooks de la fonctionnalité UX + sécurité de connexion.
 *
 * Modèle d'application (audit 2026-11-17, SEC-01) :
 * - le verrou rate-limit est appliqué via le filtre `wp_authenticate_user`
 *   (résolution du compte faite, AVANT la vérification du mot de passe) :
 *   un WP_Error retourné là ne peut pas être écrasé par le cœur, même à
 *   identifiants valides ;
 * - le nonce est ré-appliqué en fin de chaîne `authenticate` (priorité 100,
 *   après wp_authenticate_spam_check à 99) : une soumission à identifiants
 *   valides sans nonce est refusée — le formulaire natif devient réellement
 *   inert, indépendamment de l'état du CSS.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\LoginExperience;

use AinatrixLive\Hooks\HookSubscriberInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class LoginExperienceHooks implements HookSubscriberInterface {

    private const NONCE_ACTION = 'aix_live_login_form';
    private const NONCE_FIELD = 'aix_live_login_nonce';

    public function __construct(private readonly LoginRateLimiter $rateLimiter = new LoginRateLimiter()) {}

    public function register(): void {
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets']);
        add_action('login_footer', [$this, 'renderMarketingThemeSwitch']);
        add_filter('login_message', [$this, 'renderCustomLoginTemplate']);
        add_filter('login_body_class', [$this, 'addBodyClass']);
        add_filter('login_headerurl', [$this, 'filterHeaderUrl']);
        add_filter('login_headertext', [$this, 'filterHeaderText']);
        add_filter('login_title', [$this, 'filterPageTitle'], 10, 2);
        add_action('login_init', [$this, 'maybeRedirectAuthenticatedUser']);

        add_filter('wp_authenticate_user', [$this, 'blockUserWhenRateLimited'], 10, 2);
        add_filter('authenticate', [$this, 'enforceLoginContract'], 100, 1);
        add_action('wp_login_failed', [$this, 'recordFailedLogin'], 10, 2);
        add_action('wp_login', [$this, 'resetRateLimitOnSuccess'], 10, 2);
        add_filter('login_errors', [$this, 'filterLoginErrors']);
    }

    /**
     * Charge les assets premium sur wp-login.php.
     */
    public function enqueueLoginAssets(): void {
        wp_enqueue_style(
            'aix-live-login',
            AIX_LIVE_URL . 'assets/css/aix-live-login.css',
            [],
            AIX_LIVE_VERSION
        );
        wp_enqueue_script(
            'aix-live-login',
            AIX_LIVE_URL . 'assets/js/aix-live-login.js',
            ['jquery'],
            AIX_LIVE_VERSION,
            true
        );
        wp_localize_script('aix-live-login', 'aixLiveLogin', [
            'theme' => [
                'toggleLabel' => __('Basculer le thème', 'ainatrix'),
                'dark'        => __('Dark', 'ainatrix'),
                'light'       => __('Light', 'ainatrix'),
            ],
        ]);
    }

    /**
     * Ajoute une classe CSS de thème sur la page de connexion.
     *
     * @param string[] $classes
     * @return string[]
     */
    public function addBodyClass(array $classes): array {
        $classes[] = 'aix-live-login-premium';
        if ($this->isStandardLoginAction()) {
            $classes[] = 'aix-live-login-custom-form';
        }

        return array_values(array_unique($classes));
    }

    /**
     * Lien du logo de la page de connexion.
     */
    public function filterHeaderUrl(string $url): string {
        return home_url('/');
    }

    /**
     * Texte title/aria-label du logo.
     */
    public function filterHeaderText(string $text): string {
        return sprintf(
            '%1$s — %2$s',
            get_bloginfo('name'),
            __('ainatrix live', 'ainatrix')
        );
    }

    /**
     * Injecte un bloc de formulaire personnalisé avant le formulaire natif.
     */
    public function renderCustomLoginTemplate(string $message): string {
        if (!$this->isStandardLoginAction()) {
            return $message;
        }

        $templatePath = AIX_LIVE_PLUGIN_DIR . 'templates/login/form.php';
        if (!is_readable($templatePath)) {
            return $message;
        }

        $context = [
            'brand'          => __('ainatrix', 'ainatrix'),
            'ariaLabel'      => __('Connexion sécurisée', 'ainatrix'),
            'usernameLabel'  => __('Identifiant ou e-mail', 'ainatrix'),
            'passwordLabel'  => __('Mot de passe', 'ainatrix'),
            'submitLabel'    => __('Se connecter', 'ainatrix'),
            'lostPassword'   => __('Mot de passe oublié ?', 'ainatrix'),
            'backToSite'     => __('Retour au site', 'ainatrix'),
            'loginUrl'       => wp_login_url(),
            'redirectTo'     => $this->resolveRedirectTo(),
            'remember'       => isset($_POST['rememberme']),
            'username'       => $this->resolvePostedUsername(),
            'nonceFieldName' => self::NONCE_FIELD,
            'nonceValue'     => wp_create_nonce(self::NONCE_ACTION),
        ];

        ob_start();
        include $templatePath;
        $template = (string) ob_get_clean();

        return $template . $message;
    }

    /**
     * Titre de la page HTML.
     */
    public function filterPageTitle(string $loginTitle, string $title): string {
        return sprintf(
            '%1$s | %2$s',
            __('Connexion sécurisée', 'ainatrix'),
            get_bloginfo('name')
        );
    }

    /**
     * Rend le switch de thème en bas de page login.
     */
    public function renderMarketingThemeSwitch(): void {
        echo '<button type="button" class="aix-login-theme-switch" data-aix-theme-switch aria-live="polite" aria-label="';
        echo esc_attr__('Basculer le thème', 'ainatrix');
        echo '">';
        echo '<span class="aix-login-theme-switch__label">' . esc_html__('Dark', 'ainatrix') . '</span>';
        echo '</button>';
    }

    /**
     * Redirige un utilisateur déjà connecté uniquement sur l'action login.
     */
    public function maybeRedirectAuthenticatedUser(): void {
        if (!is_user_logged_in() || !$this->isStandardLoginAction()) {
            return;
        }

        $reauth = sanitize_text_field((string) wp_unslash($_REQUEST['reauth'] ?? '0'));
        if ($reauth === '1') {
            return;
        }

        wp_safe_redirect(admin_url());
        exit;
    }

    /**
     * Verrou utilisateur : appelé via `wp_authenticate_user`, c'est-à-dire une
     * fois le compte résolu (login OU e-mail) et AVANT la vérification du mot
     * de passe. Un WP_Error ici est définitif : le cœur ne peut pas le
     * surcharger, même si les identifiants soumis sont valides (audit SEC-01).
     *
     * @param \WP_User|\WP_Error|null $user
     */
    public function blockUserWhenRateLimited($user, string $password = '') {
        if (!$user instanceof \WP_User || !$this->isStandardLoginPost()) {
            return $user;
        }

        $normalizedUsername = LoginInput::username($user->user_login);
        $ipAddress = LoginInput::ipAddress();

        if (!$this->rateLimiter->isLocked($normalizedUsername, $ipAddress)) {
            return $user;
        }

        do_action(
            'aix_live_login_rate_limited',
            $normalizedUsername,
            $ipAddress,
            $this->rateLimiter->getLockRemainingSeconds($normalizedUsername, $ipAddress)
        );
        $this->applyFailureDelay();

        return new \WP_Error('aix_live_rate_limited', $this->errorMessage());
    }

    /**
     * Contrat de soumission : nonce obligatoire sur `action=login`, appliqué en
     * FIN de chaîne `authenticate` (priorité 100, après wp_authenticate_spam_check).
     * Placé ici, le refus ne peut pas être écrasé par les filtres du cœur : une
     * soumission à identifiants valides sans nonce échoue, ce qui rend le
     * formulaire natif réellement inert côté serveur (audit SEC-01/SEC-02).
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function enforceLoginContract($user) {
        if (!$this->isStandardLoginPost()) {
            return $user;
        }
        if (!$this->isCustomNonceValid()) {
            $this->applyFailureDelay();

            return new \WP_Error('aix_live_invalid_login_request', $this->errorMessage());
        }

        return $user;
    }

    /**
     * Incrémente le compteur après un échec de connexion.
     */
    public function recordFailedLogin(string $username, ?\WP_Error $error = null): void {
        if (!$this->isStandardLoginPost()) {
            return;
        }
        if ($error instanceof \WP_Error && in_array($error->get_error_code(), ['aix_live_rate_limited', 'aix_live_invalid_login_request', 'aix_live_recaptcha_failed'], true)) {
            // Échec déjà sanctionné (verrou actif, nonce absent ou captcha
            // refusé) : ne pas re-compter ni re-delay, sinon double pénalité
            // de worker (SEC-04) — l'échec captcha ne nourrit jamais le
            // verrou (anti-DoS lockout).
            return;
        }

        $normalizedUsername = LoginInput::username($username);
        $ipAddress = LoginInput::ipAddress();
        $this->rateLimiter->recordFailure($normalizedUsername, $ipAddress);
        $this->applyFailureDelay();

        do_action(
            'aix_live_login_failed_attempt',
            $normalizedUsername,
            $ipAddress,
            $error instanceof \WP_Error ? $error->get_error_codes() : []
        );
    }

    /**
     * Réinitialise le compteur à la connexion réussie.
     */
    public function resetRateLimitOnSuccess(string $userLogin, \WP_User $user): void {
        $this->rateLimiter->reset(LoginInput::username($userLogin), LoginInput::ipAddress());
    }

    /**
     * Rend le message d'erreur générique côté UI.
     */
    public function filterLoginErrors(string $errors): string {
        if (!$this->isStandardLoginAction() || $errors === '') {
            return $errors;
        }

        return sprintf(
            '<strong>%1$s</strong> %2$s',
            esc_html__('Erreur :', 'ainatrix'),
            esc_html($this->errorMessage())
        );
    }

    private function isStandardLoginAction(): bool {
        $action = sanitize_key((string) wp_unslash($_REQUEST['action'] ?? 'login'));

        return $action === 'login';
    }

    private function isStandardLoginPost(): bool {
        if (!$this->isStandardLoginAction()) {
            return false;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return false;
        }

        return isset($_POST['log'], $_POST['pwd']);
    }

    /**
     * Message d'erreur générique (anti-énumération). Littéral inline : une
     * constante passée à __() n'est pas extraite par les outils i18n (SEC-10).
     */
    private function errorMessage(): string {
        return __('Identifiants invalides ou tentative temporairement bloquée. Veuillez réessayer.', 'ainatrix');
    }

    /**
     * Rôle réel du nonce (audit SEC-03) : ralentisseur anti-bot et marqueur
     * « formulaire custom utilisé ». Un nonce anonyme n'est PAS une protection
     * CSRF (déorable sans session : n'importe qui peut fetcher wp-login.php et
     * réobtenir le nonce user 0). Opérationnel : TTL nonce 12–24 h — ne jamais
     * mettre wp-login.php en cache CDN/page, sinon nonce périmé pour tous.
     * Kill switch de secours si un flux tiers poste sans nonce sur wp-login.php.
     */
    private function isCustomNonceValid(): bool {
        $required = (bool) apply_filters('aix_live_login_require_nonce', true);
        if (!$required) {
            return true;
        }

        $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field((string) wp_unslash($_POST[self::NONCE_FIELD])) : '';
        if ($nonce === '') {
            return false;
        }

        return wp_verify_nonce($nonce, self::NONCE_ACTION) === 1;
    }

    private function resolveRedirectTo(): string {
        $default = admin_url();
        $raw = isset($_REQUEST['redirect_to']) ? (string) wp_unslash($_REQUEST['redirect_to']) : '';

        return wp_validate_redirect($raw, $default);
    }

    private function resolvePostedUsername(): string {
        if (!isset($_POST['log']) || !is_string($_POST['log'])) {
            return '';
        }

        return sanitize_text_field((string) wp_unslash($_POST['log']));
    }

    /**
     * Délai aléatoire sur échec : masque le timing bcrypt (anti-énumération).
     * Fenêtre volontairement courte (SEC-04) : un délai long endort un worker
     * PHP par requête et devient un vecteur d'épuisement de pool sous attaque.
     */
    private function applyFailureDelay(): void {
        $min = (int) apply_filters('aix_live_login_delay_min_us', 100000);
        $max = (int) apply_filters('aix_live_login_delay_max_us', 300000);

        if ($min < 0) {
            $min = 0;
        }
        if ($max < $min) {
            $max = $min;
        }

        usleep(wp_rand($min, $max));
    }
}
