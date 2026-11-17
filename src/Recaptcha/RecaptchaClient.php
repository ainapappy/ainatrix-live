<?php
/**
 * Client de vérification serveur reCAPTCHA v3 (endpoint siteverify Google).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Recaptcha;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interroge l'endpoint siteverify de Google et normalise la réponse en
 * RecaptchaVerdict. Aucune exception ne franchit cette couche : verify()
 * retourne toujours un verdict. Le token soumis et la clé secrète ne sont
 * jamais loggés ni exposés (le do_action final ne transporte que le verdict).
 */
final class RecaptchaClient {

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private readonly RecaptchaConfig $config = new RecaptchaConfig()) {}

    /**
     * Vérifie un token côté serveur pour un formulaire donné.
     *
     * Chaîne de décision : appel Google → verdict normalisé (erreur réseau =
     * verdict non fiable, défaut fail-closed) → kill switch fail-open filtrable
     * → filtre aix_live_recaptcha_verdict → do_action aix_live_recaptcha_failed
     * si le verdict final n'est pas fiable.
     *
     * @param string $token Token reçu du champ caché (peut être vide).
     * @param string $form  Identifiant du formulaire ('login', 'settings').
     */
    public function verify(string $token, string $form): RecaptchaVerdict {
        if ($token === '') {
            // Refus immédiat sans appel réseau (les hooks filtrent déjà, ce
            // garde rend le client sûr appelé directement).
            return new RecaptchaVerdict(false, 0.0, ['missing-input-response'], false);
        }

        $secret = $this->config->secretKey();
        if ($secret === '') {
            // Non configuré : fail-closed, aucun appel réseau.
            return new RecaptchaVerdict(false, 0.0, ['secret-not-configured'], false);
        }

        $timeout = (int) apply_filters('aix_live_recaptcha_timeout', 5);
        $timeout = max(2, min(10, $timeout));

        $response = wp_remote_post(self::VERIFY_URL, [
            // Pas de 'remoteip' : REMOTE_ADDR est faux derrière un CDN/proxy,
            // transmettre une IP incohérente ferait baisser le score.
            'timeout' => $timeout,
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
            ],
        ]);

        $verdict = $this->parseResponse($response);

        if ($verdict->networkError && apply_filters('aix_live_recaptcha_fail_open', false, $form)) {
            // Kill switch : ne pas bloquer les utilisateurs si Google tombe
            // (opt-in par formulaire). networkError reste à true pour tracer
            // le verdict synthétique ; le message client reste générique.
            $verdict = new RecaptchaVerdict(true, 1.0, [], true);
        }

        $verdict = apply_filters('aix_live_recaptcha_verdict', $verdict, $form);
        if (!$verdict->isTrusted($this->config->threshold())) {
            do_action('aix_live_recaptcha_failed', $form, $verdict);
        }

        return $verdict;
    }

    /**
     * Convertit la réponse HTTP brute en verdict. Toute réponse non 2xx ou
     * non décodable (page d'erreur proxy, HTML) vaut « réseau » : on ne peut
     * pas lui faire confiance pour statuer.
     *
     * @param mixed[]|\WP_Error $response
     */
    private function parseResponse($response): RecaptchaVerdict {
        if (is_wp_error($response)) {
            return RecaptchaVerdict::networkFailure();
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($body)) {
            return RecaptchaVerdict::networkFailure();
        }

        $score = isset($body['score']) && is_numeric($body['score']) ? (float) $body['score'] : 0.0;
        $errorCodes = isset($body['error-codes']) && is_array($body['error-codes'])
            ? array_values(array_filter(array_map('strval', $body['error-codes'])))
            : [];

        return new RecaptchaVerdict((bool) ($body['success'] ?? false), $score, $errorCodes, false);
    }
}
