<?php
/**
 * Données exposées au JS du panneau de publication sociale (éditeur).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\EditorPanel;

use AinatrixLive\Providers\BrandIcons;
use AinatrixLive\Providers\ProviderConfig;
use AinatrixLive\Providers\ProviderRegistry;
use AinatrixLive\SocialSettings\SocialSettingsHooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Construit l'objet localisé `aixLiveEditor` : liste des réseaux (icône de
 * marque, label, compte cible, état de configuration), URL de la page de
 * réglages et nonce du futur appel de publication.
 */
final class EditorContext {

    public function __construct(private readonly ProviderRegistry $registry) {}

    /**
     * Données sérialisées pour wp_localize_script.
     *
     * @return array<string, mixed>
     */
    public function data(): array {
        $networks = [];
        foreach ($this->registry->all() as $key => $provider) {
            $key = (string) $key;
            $brand = BrandIcons::all()[$key] ?? null;
            $configured = $provider->isConfigured();
            $networks[] = [
                'key'        => $key,
                'label'      => $provider->label(),
                'color'      => is_array($brand) ? (string) $brand['color'] : '',
                'path'       => is_array($brand) ? (string) $brand['path'] : '',
                'gradient'   => BrandIcons::isGradient($key),
                'configured' => $configured,
                'account'    => $configured
                    ? $this->accountLabel($key, ProviderConfig::load($key, $provider->defaultSettings()))
                    : '',
            ];
        }

        return [
            'settingsUrl' => admin_url('admin.php?page=' . SocialSettingsHooks::PAGE_SLUG),
            'nonce'       => wp_create_nonce(EditorPanelHooks::PUBLISH_NONCE_ACTION),
            'networks'    => $networks,
        ];
    }

    /**
     * Sous-libellé du compte cible d'un réseau configuré (lève toute
     * ambiguïté sur la destination de la publication).
     */
    private function accountLabel(string $key, ProviderConfig $config): string {
        return match ($key) {
            'facebook' => sprintf(
                __('Page — %s', 'ainatrix'),
                $config->string('page_id') !== '' ? $config->string('page_id') : __('ID manquant', 'ainatrix')
            ),
            'linkedin' => $config->string('org_id') !== ''
                ? sprintf(__('Page entreprise — %s', 'ainatrix'), $config->string('org_id'))
                : __('Profil personnel', 'ainatrix'),
            'tiktok'   => __('Compte Business TikTok', 'ainatrix'),
            'instagram' => sprintf(
                __('Compte — %s', 'ainatrix'),
                $config->string('ig_user_id') !== '' ? $config->string('ig_user_id') : __('ID manquant', 'ainatrix')
            ),
            default    => '',
        };
    }
}
