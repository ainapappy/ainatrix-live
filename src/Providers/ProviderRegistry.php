<?php
/**
 * Registre des providers de réseaux sociaux.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Point d'entrée unique de la couche Providers pour les features du plugin
 * (file de publication, UI de réglages…). Instanciation paresseuse : aucun
 * provider n'est construit tant que personne ne publie ni n'affiche l'état.
 *
 * Extensible : le filtre `ainatrix-live/providers` reçoit la liste des
 * providers — un tiers peut y ajouter une classe implémentant
 * {@see ProviderInterface} ou en retirer une.
 */
final class ProviderRegistry {

    /** @var array<string, ProviderInterface>|null Construit à la première demande. */
    private ?array $providers = null;

    /**
     * Providers indexés par clé de réseau, après application du filtre.
     *
     * @return array<string, ProviderInterface>
     */
    public function all(): array {
        if ($this->providers === null) {
            $candidates = apply_filters(
                'ainatrix-live/providers',
                [
                    new FacebookProvider(),
                    new LinkedInProvider(),
                    new TikTokProvider(),
                    new InstagramProvider(),
                ],
                $this
            );

            $providers = [];
            foreach (is_array($candidates) ? $candidates : [] as $candidate) {
                if ($candidate instanceof ProviderInterface) {
                    $providers[$candidate->key()] = $candidate;
                }
            }

            $this->providers = $providers;
        }

        return $this->providers;
    }

    /**
     * True si le réseau est enregistré.
     */
    public function has(string $key): bool {
        return isset($this->all()[$key]);
    }

    /**
     * Provider d'un réseau.
     *
     * @throws ProviderException Si la clé est inconnue.
     */
    public function get(string $key): ProviderInterface {
        $provider = $this->all()[$key] ?? null;
        if (!$provider instanceof ProviderInterface) {
            throw new ProviderException(
                sprintf(__('Réseau social inconnu : %s', 'ainatrix'), $key),
                $key
            );
        }

        return $provider;
    }

    /**
     * Providers dont la configuration est complète, indexés par clé.
     *
     * @return array<string, ProviderInterface>
     */
    public function configured(): array {
        return array_filter(
            $this->all(),
            static fn (ProviderInterface $provider): bool => $provider->isConfigured()
        );
    }
}
