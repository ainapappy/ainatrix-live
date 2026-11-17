<?php
/**
 * Verdict final d'une vérification reCAPTCHA v3.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Recaptcha;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DTO immuable portant le résultat normalisé d'une vérification reCAPTCHA v3.
 * Ne contient jamais le token soumis ni le secret : uniquement des codes
 * d'erreur Google (chaînes publiques documentées) et des booléens.
 */
final class RecaptchaVerdict {

    /**
     * @param bool     $valid        Réponse « success: true » de Google.
     * @param float    $score        Score v3 renvoyé par Google (0.0–1.0), 0.0 si absent.
     * @param string[] $errorCodes   Codes d'erreur Google (jamais le token).
     * @param bool     $networkError Vrai si Google était injoignable ou réponse illisible,
     *                               ou si le verdict est synthétique (kill switch fail-open).
     */
    public function __construct(
        public readonly bool $valid,
        public readonly float $score,
        public readonly array $errorCodes,
        public readonly bool $networkError,
    ) {}

    /**
     * Décision : verdict positif et score au-dessus du seuil. Les échecs
     * réseau restent non fiables car ils portent valid=false (jamais modifié)
     * ; le kill switch fail-open produit justement un verdict valide pour
     * franchir ce contrôle, en conservant networkError=true comme trace.
     */
    public function isTrusted(float $threshold): bool {
        return $this->valid && $this->score >= $threshold;
    }

    /**
     * Verdict par défaut quand Google est injoignable ou que sa réponse est
     * illisible : non fiable, sans score ni code exploitable.
     */
    public static function networkFailure(): self {
        return new self(false, 0.0, [], true);
    }
}
