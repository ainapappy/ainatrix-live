<?php
/**
 * Provider TikTok — publication vidéo via l'API Content Posting.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provider en attente : l'app TikTok doit être approuvée pour l'API Content
 * Posting et un article WordPress ne contient pas de vidéo à publier. La
 * structure (config, secrets, gabarit) est en place ; doPublish() signale un
 * échec explicite tant que la stratégie de contenu vidéo n'est pas définie.
 *
 * Secrets (.env) : AIX_LIVE_TIKTOK_CLIENT_SECRET, AIX_LIVE_TIKTOK_ACCESS_TOKEN.
 */
final class TikTokProvider extends AbstractProvider {

    /** Limite de la légende d'une vidéo TikTok. */
    private const MAX_MESSAGE_LENGTH = 2200;

    public function key(): string {
        return 'tiktok';
    }

    public function label(): string {
        return __('TikTok', 'ainatrix');
    }

    public function testConnection(): ProviderResult {
        if (!$this->isConfigured()) {
            return ProviderResult::failure($this->notReadyMessage());
        }

        return ProviderResult::failure($this->notReadyMessage());
    }

    protected function doPublish(PostPayload $payload, string $message): ProviderResult {
        return ProviderResult::failure($this->notReadyMessage());
    }

    /**
     * Message d'échec partagé tant que l'intégration n'est pas livrée.
     */
    private function notReadyMessage(): string {
        return __('TikTok : intégration de l’API Content Posting pas encore disponible (app en attente d’approbation).', 'ainatrix');
    }

    /**
     * @return list<string>
     */
    protected function requiredSecrets(): array {
        return ['CLIENT_SECRET'];
    }

    /**
     * @return list<string>
     */
    protected function requiredSettings(): array {
        return ['client_key'];
    }

    protected function maxMessageLength(): int {
        return self::MAX_MESSAGE_LENGTH;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array {
        return [
            'client_key'       => '',
            'privacy'          => 'PUBLIC_TO_EVERYONE',
            'default_hashtags' => '',
            'template'         => __('{titre} {hashtags}', 'ainatrix'),
            'auto_publish'     => false,
            'timeout'          => ProviderConfig::DEFAULT_TIMEOUT,
        ];
    }
}
