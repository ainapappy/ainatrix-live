<?php
/**
 * Exception de la couche Providers du plugin ainatrix live.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seule exception levée par la couche Providers. Embarque le contexte de
 * l'échec (réseau, code HTTP, extrait de réponse) sans jamais contenir de
 * secret : ni les en-têtes de requête, ni un token.
 */
final class ProviderException extends \RuntimeException {

    /**
     * @param string $message     Message lisible (déjà échappé des tags si issu d'une API).
     * @param string $providerKey Clé du réseau concerné ('facebook', 'linkedin', …).
     * @param int    $httpCode    Code HTTP de la réponse fautive, 0 si absent.
     * @param string $bodyExcerpt Extrait court du corps de réponse (sans tag), à usage diagnostic.
     */
    public function __construct(
        string $message,
        private readonly string $providerKey = '',
        private readonly int $httpCode = 0,
        private readonly string $bodyExcerpt = '',
    ) {
        parent::__construct($message, $httpCode);
    }

    public function providerKey(): string {
        return $this->providerKey;
    }

    public function httpCode(): int {
        return $this->httpCode;
    }

    public function bodyExcerpt(): string {
        return $this->bodyExcerpt;
    }
}
