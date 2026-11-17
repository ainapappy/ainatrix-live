<?php
/**
 * Normalisation des entrées de connexion (source unique, audit 2026-11-17 QA-04).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\LoginExperience;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalisation partagée entre la classe de hooks (payloads d'événements) et
 * le rate limiter (clés de buckets) : une seule implémentation, sinon risque
 * de divergence → buckets incohérents (compteur compté sous un nom, verrou
 * vérifié sous un autre).
 */
final class LoginInput {

    /**
     * Normalise un identifiant soumis : trim, sanitize, minuscule.
     * Vide → '__empty__' (clé de bucket stable pour les soumissions sans login).
     */
    public static function username(string $username): string {
        $username = trim(sanitize_text_field($username));
        if ($username === '') {
            return '__empty__';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username);
    }

    /**
     * Adresse IP du client : uniquement REMOTE_ADDR (header X-Forwarded-For
     * falsifiable). Invalide ou absente → '0.0.0.0'.
     *
     * Attention en production : si l'hébergement (reverse proxy, CDN) ne
     * restaure pas l'IP client dans REMOTE_ADDR, tous les visiteurs partagent
     * le bucket IP du rate limiter — lockouts globaux possibles.
     */
    public static function ipAddress(): string {
        return self::ip((string) wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /**
     * Normalise une IP fournie (trim, sanitize, validation). Invalide → '0.0.0.0'.
     */
    public static function ip(string $ipAddress): string {
        $ipAddress = trim(sanitize_text_field($ipAddress));

        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : '0.0.0.0';
    }
}
