<?php
/**
 * Rendu des gabarits de message des réseaux sociaux.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Substitution des placeholders ({titre}, {extrait}, …) dans le gabarit de
 * message configuré pour chaque réseau. Réutilisable par l'UI d'administration
 * pour l'aide contextuelle (PLACEHOLDERS).
 */
final class MessageTemplate {

    /**
     * Placeholders disponibles dans les gabarits de message.
     *
     * @var list<string>
     */
    public const PLACEHOLDERS = ['{titre}', '{extrait}', '{lien}', '{lien_court}', '{hashtags}'];

    /**
     * Rend le message final en substituant les placeholders du gabarit.
     */
    public static function render(string $template, PostPayload $payload): string {
        $message = strtr($template, [
            '{titre}'      => $payload->title,
            '{extrait}'    => $payload->excerpt,
            '{lien}'       => $payload->permalink,
            '{lien_court}' => $payload->shortlink !== '' ? $payload->shortlink : $payload->permalink,
            '{hashtags}'   => $payload->hashtags,
        ]);

        return trim(wp_strip_all_tags($message));
    }

    /**
     * Tronque le message à la longueur maximale du réseau (UTF-8 safe).
     */
    public static function truncate(string $message, int $maxLength): string {
        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }

        return rtrim((string) mb_strimwidth($message, 0, $maxLength, '…'));
    }
}
