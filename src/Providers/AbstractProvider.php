<?php
/**
 * Base commune des providers de réseaux sociaux.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factorise tout ce qui est transversal aux réseaux sociaux : vérification de
 * la configuration, rendu du gabarit de message, troncature, requêtes HTTP
 * (wp_remote_request) et conversion des erreurs API en ProviderResult.
 *
 * Chaque provider concret n'implémente plus que sa logique d'API :
 * {@see self::doPublish()}, ses champs requis et sa longueur de message.
 */
abstract class AbstractProvider implements ProviderInterface {

    /** En-têtes de la dernière réponse HTTP (usage : IDs renvoyés en en-tête). */
    private array $lastResponseHeaders = [];

    /**
     * Publie l'article : gabarit → troncature → API, échec normalisé.
     */
    final public function publish(PostPayload $payload): ProviderResult {
        $missing = $this->missingSettings();
        if ($missing !== []) {
            return ProviderResult::failure(
                sprintf(
                    /* translators: 1: nom du réseau, 2: liste des champs manquants. */
                    __('%1$s : configuration incomplète, champs manquants — %2$s.', 'ainatrix'),
                    $this->label(),
                    implode(', ', $missing)
                )
            );
        }

        $message = MessageTemplate::render($this->config()->template(), $payload);
        $message = MessageTemplate::truncate($message, $this->maxMessageLength());

        try {
            return $this->doPublish($payload, $message);
        } catch (ProviderException $exception) {
            return ProviderResult::failure($exception->getMessage(), $exception->httpCode());
        }
    }

    final public function isConfigured(): bool {
        return $this->missingSettings() === [];
    }

    final public function missingSettings(): array {
        $missing = [];
        foreach ($this->requiredSettings() as $key) {
            if ($this->config()->string($key) === '') {
                $missing[] = $key;
            }
        }
        foreach ($this->requiredSecrets() as $suffix) {
            if (!Secrets::hasFor($this->key(), $suffix)) {
                $missing[] = Secrets::envName($this->key(), $suffix);
            }
        }

        return $missing;
    }

    /**
     * Publication réelle vers l'API du réseau (configuration vérifiée).
     * Peut lever ProviderException : publish() la convertit en échec.
     */
    abstract protected function doPublish(PostPayload $payload, string $message): ProviderResult;

    /**
     * Suffixes .env requis (ex. ['APP_SECRET', 'PAGE_TOKEN']).
     *
     * @return list<string>
     */
    abstract protected function requiredSecrets(): array;

    /**
     * Champs de configuration requis (ex. ['page_id']).
     *
     * @return list<string>
     */
    abstract protected function requiredSettings(): array;

    /**
     * Longueur maximale du message acceptée par le réseau.
     */
    abstract protected function maxMessageLength(): int;

    /**
     * Configuration du réseau (défauts + option + .env).
     */
    final protected function config(): ProviderConfig {
        return ProviderConfig::load($this->key(), $this->defaults());
    }

    /**
     * Lit un secret du .env pour ce réseau.
     */
    final protected function secret(string $suffix): string {
        return Secrets::forNetwork($this->key(), $suffix);
    }

    /**
     * Défauts de configuration du réseau (fusionnés sous l'option et le .env).
     *
     * @return array<string, mixed>
     */
    final public function defaultSettings(): array {
        return $this->defaults();
    }

    /**
     * Défauts de configuration du réseau (fusionnés sous l'option et le .env).
     *
     * @return array<string, mixed>
     */
    protected function defaults(): array {
        return [];
    }

    /**
     * Requête HTTP vers l'API du réseau.
     *
     * - timeout issu de la configuration (borné 5–120 s), sslverify actif ;
     * - corps JSON ($json = true) ou formulaire encodé, au choix de l'API ;
     * - WP_Error et statut >= 400 lèvent ProviderException (message API si
     *   disponible, extrait de corps court en diagnostic) ;
     * - ne journalise jamais les en-têtes (Authorization, tokens).
     *
     * @param array<string, mixed> $body    Corps de la requête.
     * @param array<string, string> $headers En-têtes additionnels (ex. Bearer).
     * @return array<string, mixed> Réponse décodée (tableau vide si non JSON).
     * @throws ProviderException En cas d'erreur réseau ou HTTP.
     */
    final protected function request(string $method, string $url, array $body = [], array $headers = [], bool $json = false): array {
        $args = [
            'method'    => strtoupper($method),
            'timeout'   => $this->config()->timeout(),
            'blocking'  => true,
            'sslverify' => true,
            'headers'   => $headers,
        ];

        if ($body !== []) {
            if ($json) {
                $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
                $args['body'] = (string) wp_json_encode($body);
            } else {
                $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
                $args['body'] = build_query($body);
            }
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            throw new ProviderException($response->get_error_message(), $this->key());
        }

        $headers = wp_remote_retrieve_headers($response);
        $this->lastResponseHeaders = is_iterable($headers) ? iterator_to_array($headers) : [];

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);

        $data = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if ($code < 200 || $code >= 400) {
            throw new ProviderException($this->apiErrorMessage($data, $code), $this->key(), $code, $this->bodyExcerpt($raw));
        }

        return $data;
    }

    /**
     * Extrait un message d'erreur lisible d'une réponse API (formats Facebook,
     * LinkedIn, TikTok et Instagram couverts par les trois premières clés).
     *
     * @param array<string, mixed> $data Réponse décodée.
     */
    protected function apiErrorMessage(array $data, int $code): string {
        $error = $data['error'] ?? null;
        $message = '';
        if (is_array($error)) {
            $message = (string) ($error['message'] ?? '');
        } elseif (is_string($error)) {
            $message = $error;
        }
        if ($message === '' && isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        }
        if ($message === '' && isset($data['error_description']) && is_string($data['error_description'])) {
            $message = $data['error_description'];
        }
        if ($message === '') {
            $message = sprintf(
                /* translators: 1: nom du réseau, 2: code HTTP. */
                __('Erreur %1$s, réponse HTTP %2$d.', 'ainatrix'),
                $this->label(),
                $code
            );
        }

        return wp_strip_all_tags($message);
    }

    /**
     * Extrait court du corps de réponse, à usage diagnostic (sans secret).
     */
    private function bodyExcerpt(string $raw): string {
        return mb_substr(wp_strip_all_tags($raw), 0, 300);
    }

    /**
     * Valeur d'un en-tête de la dernière réponse HTTP (insensible à la casse).
     */
    final protected function lastHeader(string $name): string {
        foreach ($this->lastResponseHeaders as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return '';
    }
}
