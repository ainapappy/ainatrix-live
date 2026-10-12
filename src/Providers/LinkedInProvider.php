<?php
/**
 * Provider LinkedIn — publication via l'API Posts (versionnée, /rest/posts).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Partage de texte : POST /rest/posts (API Posts, en-tête LinkedIn-Version).
 * L'app LinkedIn doit avoir « Community Management API » (publication sur une
 * page entreprise, scope w_organization_social) et/ou « Share on LinkedIn »
 * (profil personnel, scope w_member_social) approuvés.
 *
 * Secrets (.env) : AIX_LIVE_LINKEDIN_ACCESS_TOKEN (token OAuth ~60 jours),
 * AIX_LIVE_LINKEDIN_CLIENT_SECRET. Publication sur la page entreprise si le
 * champ org_id est renseigné, sinon sur le profil personnel.
 */
final class LinkedInProvider extends AbstractProvider {

    /** URL de base de l'API LinkedIn. */
    private const API_BASE = 'https://api.linkedin.com';

    /** Version de l'API Posts par défaut (format AAAAMM, support ~12 mois). */
    private const DEFAULT_VERSION = '202601';

    /** Version du protocole Rest.li exigée par l'API Posts. */
    private const RESTLI_PROTOCOL_VERSION = '2.0.0';

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
            $profile = $this->request('GET', $this->apiBase() . '/rest/me', [], $this->apiHeaders());
        } catch (ProviderException $exception) {
            // Repli : l'endpoint OIDC userinfo si /rest/me n'est pas accessible au token.
            try {
                $profile = $this->request('GET', $this->apiBase() . '/userinfo', [], $this->apiHeaders());
            } catch (ProviderException $fallbackException) {
                return ProviderResult::failure($fallbackException->getMessage(), $fallbackException->httpCode());
            }
        }

        return ProviderResult::success((string) ($profile['sub'] ?? ''));
    }

    protected function doPublish(PostPayload $payload, string $message): ProviderResult {
        $token = $this->secret('ACCESS_TOKEN');
        $this->request('POST', $this->apiBase() . '/rest/posts', [
            'author'       => $this->authorUrn($token),
            'commentary'   => $this->escapeLittleText($message),
            'visibility'   => $this->visibility(),
            'distribution' => [
                'feedDistribution'               => 'MAIN_FEED',
                'targetEntities'                 => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState'            => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ], $this->apiHeaders($token), true);

        // L'URN du post (ex. urn:li:post:1234) est renvoyé dans l'en-tête x-restli-id.
        $remoteId = $this->lastHeader('x-restli-id');

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
        $profile = $this->request('GET', $this->apiBase() . '/userinfo', [], ['Authorization' => 'Bearer ' . $token]);

        return (string) ($profile['sub'] ?? '');
    }

    /**
     * Visibilité du post (maquette : Public / Connexions uniquement).
     */
    private function visibility(): string {
        return $this->config()->string('visibility', 'PUBLIC') === 'CONNECTIONS' ? 'CONNECTIONS' : 'PUBLIC';
    }

    /**
     * En-têtes communs de l'API Posts (version d'API + Rest.li).
     *
     * @return array<string, string>
     */
    private function apiHeaders(?string $token = null): array {
        $headers = [
            'LinkedIn-Version'           => $this->config()->string('api_version', self::DEFAULT_VERSION),
            'X-Restli-Protocol-Version'  => self::RESTLI_PROTOCOL_VERSION,
        ];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * URL de base de l'API (configurable, repli : const API_BASE).
     */
    private function apiBase(): string {
        return rtrim($this->config()->string('api_url', self::API_BASE), '/');
    }

    /**
     * Échappe le texte au format « little text » de LinkedIn (sinon certains
     * caractères cassent le post). Le # est préservé : nos hashtags sont
     * intentionnels, pas du texte littéral.
     */
    private function escapeLittleText(string $text): string {
        return (string) preg_replace('/([\\\\|{}@\[\]()<>_*~])/', '\\\\$1', $text);
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
            'api_url'     => self::API_BASE,
            'api_version' => self::DEFAULT_VERSION,
            'org_id'      => '',
            'visibility'  => 'PUBLIC',
            'template'    => "{titre} {lien}\n\n{extrait}\n{hashtags}",
            'auto_publish' => false,
            'timeout'     => ProviderConfig::DEFAULT_TIMEOUT,
        ];
    }
}
