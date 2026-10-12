<?php
/**
 * Provider LinkedIn — publication via l'API UGC Posts.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Partage de texte : POST /v2/ugcPosts. L'app LinkedIn doit avoir les produits
 * « Share on LinkedIn » et « Sign In with LinkedIn » approuvés.
 *
 * Secrets (.env) : AIX_LIVE_LINKEDIN_ACCESS_TOKEN, AIX_LIVE_LINKEDIN_CLIENT_SECRET.
 * Publication sur la page entreprise si le champ org_id est renseigné,
 * sinon sur le profil personnel (URN résolu via /v2/userinfo).
 */
final class LinkedInProvider extends AbstractProvider {

    /** URL de base de l'API LinkedIn. */
    private const API_BASE = 'https://api.linkedin.com/v2';

    /** Limite du texte d'un post LinkedIn. */
    private const MAX_MESSAGE_LENGTH = 3000;

    public function key(): string {
        return 'linkedin';
    }

    public function label(): string {
        return __('LinkedIn', 'ainatrix');
    }

    public function testConnection(): ProviderResult {
        try {
            $profile = $this->request('GET', self::API_BASE . '/userinfo', [], $this->authHeaders());
        } catch (ProviderException $exception) {
            return ProviderResult::failure($exception->getMessage(), $exception->httpCode());
        }

        return ProviderResult::success((string) ($profile['sub'] ?? ''));
    }

    protected function doPublish(PostPayload $payload, string $message): ProviderResult {
        $token = $this->secret('ACCESS_TOKEN');
        $data = $this->request('POST', self::API_BASE . '/ugcPosts', [
            'author'          => $this->authorUrn($token),
            'commentary'      => $message,
            'lifecycleState'  => 'PUBLISHED',
            'visibility'      => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->visibility(),
            ],
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareMediaCategory' => 'NONE',
                ],
            ],
        ], ['Authorization' => 'Bearer ' . $token], true);

        $remoteId = (string) ($data['id'] ?? '');

        return ProviderResult::success(
            $remoteId,
            $remoteId !== '' ? 'https://www.linkedin.com/feed/update/' . $remoteId : ''
        );
    }

    /**
     * URN de l'auteur : page entreprise (org_id) ou profil personnel.
     */
    private function authorUrn(string $token): string {
        $orgId = $this->config()->string('org_id');
        if ($orgId !== '') {
            return str_starts_with($orgId, 'urn:') ? $orgId : 'urn:li:organization:' . $orgId;
        }

        return 'urn:li:person:' . $this->personId($token);
    }

    /**
     * Identifiant du membre connecté, résolu via l'endpoint OIDC userinfo.
     */
    private function personId(string $token): string {
        $profile = $this->request('GET', self::API_BASE . '/userinfo', [], ['Authorization' => 'Bearer ' . $token]);

        return (string) ($profile['sub'] ?? '');
    }

    /**
     * Visibilité du post (maquette : Public / Connexions uniquement).
     */
    private function visibility(): string {
        return $this->config()->string('visibility', 'PUBLIC') === 'CONNECTIONS' ? 'CONNECTIONS' : 'PUBLIC';
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array {
        return ['Authorization' => 'Bearer ' . $this->secret('ACCESS_TOKEN')];
    }

    /**
     * @return list<string>
     */
    protected function requiredSecrets(): array {
        return ['ACCESS_TOKEN', 'CLIENT_SECRET'];
    }

    /**
     * @return list<string>
     */
    protected function requiredSettings(): array {
        return [];
    }

    protected function maxMessageLength(): int {
        return self::MAX_MESSAGE_LENGTH;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array {
        return [
            'org_id'     => '',
            'visibility' => 'PUBLIC',
            'template'   => "{titre} {lien}\n\n{extrait}\n{hashtags}",
            'auto_publish' => false,
            'timeout'    => ProviderConfig::DEFAULT_TIMEOUT,
        ];
    }
}
