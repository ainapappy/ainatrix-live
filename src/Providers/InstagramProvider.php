<?php
/**
 * Provider Instagram — publication via l'API Instagram Graph.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Publication en deux étapes : création d'un conteneur média
 * (POST /{ig-user-id}/media) puis publication (POST /{ig-user-id}/media_publish).
 * Compte Instagram Business rattaché à la page Facebook : les secrets sont
 * partagés avec le provider Facebook (même préfixe AIX_LIVE_FB_, même page token).
 *
 * Instagram n'accepte pas de lien cliquable en légende : le gabarit par défaut
 * utilise {lien} tel quel, à l'utilisateur de mettre le lien en bio via {lien_court}.
 */
final class InstagramProvider extends AbstractProvider {

    /** Limite de la légende d'un média Instagram. */
    private const MAX_MESSAGE_LENGTH = 2200;

    public function key(): string {
        return 'instagram';
    }

    public function label(): string {
        return __('Instagram', 'ainatrix');
    }

    public function testConnection(): ProviderResult {
        try {
            $data = $this->request('GET', $this->apiUrl($this->config()->string('ig_user_id'), [
                'fields'       => 'username',
                'access_token' => $this->secret('PAGE_TOKEN'),
            ]));
        } catch (ProviderException $exception) {
            return ProviderResult::failure($exception->getMessage(), $exception->httpCode());
        }

        return ProviderResult::success(
            $this->config()->string('ig_user_id'),
            isset($data['username']) ? 'https://www.instagram.com/' . $data['username'] . '/' : ''
        );
    }

    protected function doPublish(PostPayload $payload, string $message): ProviderResult {
        if ($payload->imageUrl === '') {
            return ProviderResult::failure(
                __('Instagram : aucune image mise en avant sur l’article — un média est obligatoire.', 'ainatrix')
            );
        }

        $token = $this->secret('PAGE_TOKEN');
        $userId = $this->config()->string('ig_user_id');

        $container = $this->request('POST', $this->apiUrl($userId . '/media'), [
            'image_url'    => $payload->imageUrl,
            'caption'      => $message,
            'access_token' => $token,
        ]);

        $creationId = (string) ($container['id'] ?? '');
        if ($creationId === '') {
            return ProviderResult::failure(
                __('Instagram : création du conteneur média échouée (réponse inattendue de l’API).', 'ainatrix')
            );
        }

        $published = $this->request('POST', $this->apiUrl($userId . '/media_publish'), [
            'creation_id'  => $creationId,
            'access_token' => $token,
        ]);

        return ProviderResult::success((string) ($published['id'] ?? ''));
    }

    /**
     * URL d'un endpoint Instagram Graph : https://graph.facebook.com/{version}/{chemin}.
     *
     * @param array<string, string> $query Paramètres de requête (access_token…).
     */
    private function apiUrl(string $path, array $query = []): string {
        $base = rtrim($this->config()->string('graph_url', 'https://graph.facebook.com'), '/');
        $url = $base . '/' . ltrim($path, '/');

        return $query === [] ? $url : $url . '?' . build_query($query);
    }

    /**
     * @return list<string>
     */
    protected function requiredSecrets(): array {
        return ['PAGE_TOKEN'];
    }

    /**
     * @return list<string>
     */
    protected function requiredSettings(): array {
        return ['ig_user_id'];
    }

    protected function maxMessageLength(): int {
        return self::MAX_MESSAGE_LENGTH;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array {
        return [
            'ig_user_id'   => '',
            'template'     => __("{titre}\n\nLien en bio 🔗 {lien_court}\n{hashtags}", 'ainatrix'),
            'carousel'     => false,
            'auto_publish' => false,
            'timeout'      => ProviderConfig::DEFAULT_TIMEOUT,
        ];
    }
}
