<?php
/**
 * Provider Facebook — publication sur une page via l'API Graph.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Publication de statut de page : POST /{page-id}/feed. L'app Meta doit avoir
 * les permissions pages_manage_posts et pages_read_engagement.
 *
 * Secrets (.env) : AIX_LIVE_FB_PAGE_TOKEN, AIX_LIVE_FB_APP_SECRET.
 */
final class FacebookProvider extends AbstractProvider {

    /** Version d'API Graph par défaut. */
    private const DEFAULT_API_VERSION = 'v22.0';

    /** Limite du champ message d'un feed (documentation Graph API). */
    private const MAX_MESSAGE_LENGTH = 63206;

    public function key(): string {
        return 'facebook';
    }

    public function label(): string {
        return __('Facebook', 'ainatrix');
    }

    public function testConnection(): ProviderResult {
        try {
            $data = $this->request('GET', $this->apiUrl($this->config()->string('page_id'), [
                'fields'       => 'name',
                'access_token' => $this->secret('PAGE_TOKEN'),
            ]));
        } catch (ProviderException $exception) {
            return ProviderResult::failure($exception->getMessage(), $exception->httpCode());
        }

        return ProviderResult::success(
            $this->config()->string('page_id'),
            (string) ($data['link'] ?? '')
        );
    }

    protected function doPublish(PostPayload $payload, string $message): ProviderResult {
        $data = $this->request('POST', $this->apiUrl($this->config()->string('page_id') . '/feed'), [
            'message'      => $this->applyLinkFirst($payload, $message),
            'access_token' => $this->secret('PAGE_TOKEN'),
        ]);

        $remoteId = (string) ($data['id'] ?? '');

        return ProviderResult::success($remoteId, $remoteId !== '' ? 'https://www.facebook.com/' . $remoteId : '');
    }

    /**
     * Option « Lien en priorité » : place le permalien en tête du message
     * plutôt qu'à la position du gabarit.
     */
    private function applyLinkFirst(PostPayload $payload, string $message): string {
        if (!$this->config()->bool('link_first', true)) {
            return $message;
        }

        $permalink = $payload->permalink;
        if ($permalink === '' || str_starts_with($message, $permalink)) {
            return $message;
        }

        $rest = trim(str_replace($permalink, '', $message));

        return $rest !== '' ? $permalink . "\n\n" . $rest : $permalink;
    }

    /**
     * URL d'un endpoint Graph : {graph_url}/{version}/{chemin}.
     *
     * @param array<string, string> $query Paramètres de requête (access_token…).
     */
    private function apiUrl(string $path, array $query = []): string {
        $version = $this->config()->string('api_version', self::DEFAULT_API_VERSION);
        if (preg_match('/^v\d+\.\d+$/', $version) !== 1) {
            $version = self::DEFAULT_API_VERSION;
        }

        $base = rtrim($this->config()->string('graph_url', 'https://graph.facebook.com'), '/');
        $url = $base . '/' . $version . '/' . ltrim($path, '/');

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
        return ['page_id'];
    }

    protected function maxMessageLength(): int {
        return self::MAX_MESSAGE_LENGTH;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array {
        return [
            'page_id'     => '',
            'api_version' => self::DEFAULT_API_VERSION,
            'graph_url'   => 'https://graph.facebook.com',
            'template'    => __('{titre} — {extrait} {lien}', 'ainatrix'),
            'link_first'  => true,
            'auto_publish' => false,
            'timeout'     => ProviderConfig::DEFAULT_TIMEOUT,
        ];
    }
}
