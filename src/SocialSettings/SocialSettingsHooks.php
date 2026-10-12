<?php
/**
 * Classe de hooks de la fonctionnalité Réglages réseaux sociaux.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\SocialSettings;

use AinatrixLive\Hooks\HookSubscriberInterface;
use AinatrixLive\Providers\ProviderException;
use AinatrixLive\Providers\ProviderRegistry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre toute la surface admin de la fonctionnalité : menu « ainatrix »,
 * assets (page de réglages uniquement), enregistrement du formulaire
 * (admin-post) et test de connexion (admin-ajax).
 */
final class SocialSettingsHooks implements HookSubscriberInterface {

    /** Slug de page du menu (premier sous-menu : Réseaux sociaux). */
    public const PAGE_SLUG = 'ainatrix-social';

    /** Action admin-post du formulaire d'enregistrement. */
    public const SAVE_ACTION = 'aix_live_save_settings';

    /** Action admin-ajax du test de connexion. */
    public const TEST_ACTION = 'aix_live_test_connection';

    public function __construct(private readonly ProviderRegistry $registry = new ProviderRegistry()) {}

    public function register(): void {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handleSave']);
        add_action('wp_ajax_' . self::TEST_ACTION, [$this, 'handleTestConnection']);
    }

    /**
     * Menu racine « ainatrix » : première entrée = Réseaux sociaux.
     */
    public function registerMenu(): void {
        add_menu_page(
            __('ainatrix live', 'ainatrix'),
            __('ainatrix', 'ainatrix'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-share',
            // Position 3 : tout en haut du menu, juste avant « Articles » (position 5).
            3
        );
        add_submenu_page(
            self::PAGE_SLUG,
            __('Réseaux sociaux', 'ainatrix'),
            __('Réseaux sociaux', 'ainatrix'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Charge les assets uniquement sur la page de réglages.
     */
    public function enqueueAssets(string $hookSuffix): void {
        if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'aix-live-settings',
            AIX_LIVE_URL . 'assets/css/aix-live-settings.css',
            [],
            AIX_LIVE_VERSION
        );
        wp_enqueue_script(
            'aix-live-settings',
            AIX_LIVE_URL . 'assets/js/aix-live-settings.js',
            ['jquery'],
            AIX_LIVE_VERSION,
            true
        );
        wp_localize_script('aix-live-settings', 'aixLiveSettings', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce(self::TEST_ACTION),
            'updated'    => isset($_GET['updated']) ? '1' : '0',
            'i18n'       => [
                'saved'          => __('Réglages enregistrés.', 'ainatrix'),
                'testing'        => __('Test de connexion en cours…', 'ainatrix'),
                'connected'      => __('Connecté', 'ainatrix'),
                'notConnected'   => __('Non configuré', 'ainatrix'),
                'networkError'   => __('Erreur réseau : test impossible.', 'ainatrix'),
                'missingFields'  => __('Configuration incomplète — complétez les champs requis.', 'ainatrix'),
            ],
        ]);
    }

    /**
     * Affiche la page de réglages (callback du menu).
     */
    public function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ainatrix'));
        }

        (new SettingsPage($this->registry))->render();
    }

    /**
     * Enregistrement du formulaire : nonce + capability, sanitize, redirection.
     */
    public function handleSave(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'ainatrix'));
        }
        check_admin_referer(self::SAVE_ACTION, 'aix_live_nonce');

        $raw = wp_unslash($_POST['aix_live'] ?? []);
        SettingsStore::save(SettingsStore::sanitize(is_array($raw) ? $raw : []));

        wp_safe_redirect(add_query_arg('updated', '1', menu_page_url(self::PAGE_SLUG, false)));
        exit;
    }

    /**
     * Test de connexion AJAX (bouton « Tester la connexion »).
     */
    public function handleTestConnection(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Accès refusé.', 'ainatrix')], 403);
        }
        check_ajax_referer(self::TEST_ACTION, 'nonce');

        $key = sanitize_key(wp_unslash($_POST['provider'] ?? ''));
        try {
            $provider = $this->registry->get($key);
        } catch (ProviderException) {
            wp_send_json_error(['message' => __('Réseau social inconnu.', 'ainatrix')]);
        }

        if (!$provider->isConfigured()) {
            wp_send_json_error(['message' => implode(' ', [
                __('Configuration incomplète — champs manquants :', 'ainatrix'),
                implode(', ', $provider->missingSettings()),
            ])]);
        }

        $result = $provider->testConnection();
        $message = $result->message !== '' ? $result->message : __('Connexion vérifiée.', 'ainatrix');
        if ($result->success) {
            wp_send_json_success(['message' => $message]);
        }

        wp_send_json_error(['message' => $message, 'httpCode' => $result->httpCode]);
    }
}
