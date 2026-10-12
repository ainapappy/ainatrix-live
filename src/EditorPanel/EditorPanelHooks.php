<?php
/**
 * Classe de hooks de la fonctionnalité Panneau de publication sociale (éditeur).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\EditorPanel;

use AinatrixLive\Hooks\HookSubscriberInterface;
use AinatrixLive\Providers\ProviderRegistry;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Charge les assets du panneau de publication sociale dans l'éditeur de blocs
 * (fill PluginPostStatusInfo) et expose au JS les réseaux, leur état de
 * configuration et le nonce du futur appel de publication.
 */
final class EditorPanelHooks implements HookSubscriberInterface {

    /**
     * Action du nonce vérifié côté serveur lors du futur appel de publication
     * (REST ou admin-ajax) déclenché depuis le panneau.
     */
    public const PUBLISH_NONCE_ACTION = 'aix_live_editor_publish';

    public function __construct(private readonly ProviderRegistry $registry = new ProviderRegistry()) {}

    public function register(): void {
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * Charge les assets éditeur, uniquement sur les écrans d'édition des
     * types de publication supportés et pour les utilisateurs autorisés à
     * éditer l'article courant.
     */
    public function enqueueEditorAssets(): void {
        $post = get_post();
        if (!$post instanceof \WP_Post || !$this->supportsEditor($post)) {
            return;
        }
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }

        wp_enqueue_style(
            'aix-live-editor',
            AIX_LIVE_URL . 'assets/css/aix-live-editor.css',
            [],
            AIX_LIVE_VERSION
        );
        wp_enqueue_script(
            'aix-live-editor',
            AIX_LIVE_URL . 'assets/js/aix-live-editor.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components', 'wp-i18n'],
            AIX_LIVE_VERSION,
            true
        );
        wp_localize_script(
            'aix-live-editor',
            'aixLiveEditor',
            (new EditorContext($this->registry))->data()
        );
    }

    /**
     * Types de publication dont l'éditeur affiche le panneau (filtrable).
     */
    private function supportsEditor(\WP_Post $post): bool {
        $types = (array) apply_filters('ainatrix-live/editor_post_types', ['post']);
        $types = array_map('sanitize_key', $types);

        return in_array($post->post_type, $types, true) && post_type_supports($post->post_type, 'editor');
    }
}
