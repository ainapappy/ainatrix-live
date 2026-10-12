<?php
/**
 * Configuration non secrète d'un réseau social (option aix_live_providers + .env).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accès typé à la configuration d'un provider. Ordre de résolution par champ :
 * 1. option `aix_live_providers` (tableau réseau => champs, écrite par la future
 *    UI de réglages, sanitizée à l'écriture) ;
 * 2. variable .env dérivée du nom du champ (ex. page_id → AIX_LIVE_FB_PAGE_ID) ;
 * 3. défaut déclaré par le provider.
 *
 * Les secrets ne passent jamais ici : uniquement {@see Secrets}.
 */
final class ProviderConfig {

    /** Clé d'option WordPress regroupant la configuration de tous les réseaux. */
    public const OPTION_KEY = 'aix_live_providers';

    /** Durée d'expiration HTTP par défaut, en secondes. */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * @param string             $providerKey Clé du réseau.
     * @param array<string, mixed> $defaults  Valeurs par défaut du provider.
     */
    public function __construct(
        private readonly string $providerKey,
        private readonly array $defaults = [],
    ) {}

    /**
     * Charge la configuration d'un réseau depuis l'option et le .env.
     *
     * @param array<string, mixed> $defaults Défauts déclarés par le provider.
     */
    public static function load(string $providerKey, array $defaults = []): self {
        return new self($providerKey, $defaults);
    }

    /**
     * Valeur brute d'un champ, résolue (option → .env → défaut).
     */
    public function get(string $key, mixed $default = null): mixed {
        $value = $this->settings()[$key] ?? null;
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value !== null && $value !== '') {
            return $value;
        }

        $env = Secrets::forNetwork($this->providerKey, strtoupper($key));
        if ($env !== '') {
            return $env;
        }

        return $default;
    }

    /**
     * Champ texte non vide.
     */
    public function string(string $key, string $default = ''): string {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Champ booléen (case à cocher de la maquette : publication auto, lien en priorité…).
     */
    public function bool(string $key, bool $default = false): bool {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * Champ numérique.
     */
    public function int(string $key, int $default): int {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Délai d'expiration HTTP, borné entre 5 et 120 secondes.
     */
    public function timeout(): int {
        return max(5, min(120, $this->int('timeout', self::DEFAULT_TIMEOUT)));
    }

    /**
     * Gabarit de message du réseau.
     */
    public function template(): string {
        return $this->string('template');
    }

    /**
     * Publication automatique dès qu'un article passe en « publié ».
     */
    public function autoPublish(): bool {
        return $this->bool('auto_publish', false);
    }

    /**
     * Configuration fusionnée, pour affichage dans la future UI de réglages.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return $this->settings();
    }

    /**
     * Fusion des défauts du provider et des champs enregistrés en option.
     *
     * @return array<string, mixed>
     */
    private function settings(): array {
        $option = get_option(self::OPTION_KEY);
        $network = [];
        if (is_array($option) && isset($option[$this->providerKey]) && is_array($option[$this->providerKey])) {
            $network = $option[$this->providerKey];
        }

        return array_merge($this->defaults, $network);
    }
}
