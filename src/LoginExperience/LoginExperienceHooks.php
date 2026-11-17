<?php
/**
 * Classe de hooks de la fonctionnalité UX + sécurité de connexion.
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

    private const ERROR_MESSAGE = 'Identifiants invalides ou tentative temporairement bloquée. Veuillez réessayer.';

    public function __construct(private readonly LoginRateLimiter $rateLimiter = new LoginRateLimiter()) {}

    public function register(): void {
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets']);
        add_action('login_footer', [$this, 'renderMarketingThemeSwitch']);
        add_filter('login_body_class', [$this, 'addBodyClass']);
        add_filter('login_headerurl', [$this, 'filterHeaderUrl']);
        add_filter('login_headertext', [$this, 'filterHeaderText']);
        add_filter('login_title', [$this, 'filterPageTitle'], 10, 2);
        add_action('login_init', [$this, 'maybeRedirectAuthenticatedUser']);

        add_filter('authenticate', [$this, 'blockWhenRateLimited'], 5, 3);
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
     * Bloque l'authentification en cas de lock actif.
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function blockWhenRateLimited($user, string $username, string $password) {
        if (!$this->isStandardLoginPost()) {
            return $user;
        }

        $normalizedUsername = $this->resolveUsername($username);
        $ipAddress = $this->resolveIpAddress();

        if (!$this->rateLimiter->isLocked($normalizedUsername, $ipAddress)) {
            return $user;
        }

        do_action(
            'aix_live_login_rate_limited',
            $normalizedUsername,
            $ipAddress,
            $this->rateLimiter->getLockRemainingSeconds($normalizedUsername, $ipAddress)
        );

        return new \WP_Error('aix_live_rate_limited', __(self::ERROR_MESSAGE, 'ainatrix'));
    }

    /**
     * Incrémente le compteur après un échec de connexion.
     */
    public function recordFailedLogin(string $username, ?\WP_Error $error = null): void {
        if (!$this->isStandardLoginPost()) {
            return;
        }
        if ($error instanceof \WP_Error && $error->get_error_code() === 'aix_live_rate_limited') {
            return;
        }

        $normalizedUsername = $this->resolveUsername($username);
        $ipAddress = $this->resolveIpAddress();
        $this->rateLimiter->recordFailure($normalizedUsername, $ipAddress);

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
        $this->rateLimiter->reset($this->resolveUsername($userLogin), $this->resolveIpAddress());
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
            esc_html__(self::ERROR_MESSAGE, 'ainatrix')
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

    private function resolveUsername(string $username): string {
        if ($username === '' && isset($_POST['log']) && is_string($_POST['log'])) {
            $username = (string) wp_unslash($_POST['log']);
        }

        $username = trim(sanitize_text_field($username));
        if ($username === '') {
            return '__empty__';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username);
    }

    private function resolveIpAddress(): string {
        $ipAddress = trim(sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));

        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : '0.0.0.0';
    }
}
