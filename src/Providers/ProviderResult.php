<?php
/**
 * Résultat normalisé d'une opération de provider.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DTO immuable retourné par publish() et testConnection(). Aucune exception
 * ne franchit la couche Providers : tout échec est converti en résultat.
 */
final class ProviderResult {

    /**
     * @param bool   $success   True si l'opération a réussi.
     * @param string $remoteId  Identifiant distant de la publication (si connu).
     * @param string $remoteUrl URL publique de la publication (si connue).
     * @param string $message   Message lisible en cas d'échec (i18n, sans secret).
     * @param int    $httpCode  Code HTTP en cas d'échec réseau, 0 sinon.
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $remoteId = '',
        public readonly string $remoteUrl = '',
        public readonly string $message = '',
        public readonly int $httpCode = 0,
    ) {}

    public static function success(string $remoteId = '', string $remoteUrl = ''): self {
        return new self(true, $remoteId, $remoteUrl);
    }

    public static function failure(string $message, int $httpCode = 0): self {
        return new self(false, '', '', $message, $httpCode);
    }
}
