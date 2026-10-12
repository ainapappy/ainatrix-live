<?php
/**
 * Contrat des providers de réseaux sociaux du plugin ainatrix live.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Un provider encapsule la publication d'un article vers un réseau social
 * (Facebook, LinkedIn, TikTok, Instagram). Toute nouvelle plateforme doit
 * implémenter ce contrat et être ajoutée au registre
 * {@see ProviderRegistry} (ou injectée via le filtre `ainatrix-live/providers`).
 */
interface ProviderInterface {

    /**
     * Clé unique du réseau ('facebook', 'linkedin', 'tiktok', 'instagram').
     */
    public function key(): string;

    /**
     * Libellé affichable (traduit, domain ainatrix).
     */
    public function label(): string;

    /**
     * True si tous les secrets .env et champs de config requis sont présents.
     */
    public function isConfigured(): bool;

    /**
     * Liste des champs manquants (noms de config ou suffixes .env requis),
     * pour le badge « À faire » de l'UI et les messages d'erreur.
     *
     * @return list<string>
     */
    public function missingSettings(): array;

    /**
     * Publie l'article sur le réseau. Ne lève jamais d'exception : le résultat
     * normalisé porte le succès ou l'échec.
     */
    public function publish(PostPayload $payload): ProviderResult;

    /**
     * Vérifie la connexion (bouton « Tester la connexion » de l'UI).
     */
    public function testConnection(): ProviderResult;

    /**
     * Défauts de configuration du réseau, pour l'UI de réglages.
     *
     * @return array<string, mixed>
     */
    public function defaultSettings(): array;
}
