<?php
/**
 * Schéma des champs de réglages des réseaux sociaux (miroir de la maquette).
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\SocialSettings;

use AinatrixLive\Providers\MessageTemplate;
use AinatrixLive\Providers\Secrets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Source unique des champs affichés dans la page de réglages ET des règles de
 * sanitization à l'enregistrement : ajouter un champ ici suffit pour les deux.
 *
 * Types de champ :
 * - text / number / select / checkbox : persistés dans l'option aix_live_providers ;
 * - env : lecture seule (statut défini/absent dans le .env), jamais soumis.
 */
final class SettingsSchema {

    /** Pages (réseaux) affichées, dans l'ordre de la maquette. */
    public const PAGES = ['facebook', 'linkedin', 'tiktok', 'instagram'];

    /**
     * True si la page existe dans le schéma.
     */
    public static function exists(string $page): bool {
        return in_array($page, self::PAGES, true);
    }

    /**
     * Sections de champs d'une page, dans l'ordre de la maquette.
     *
     * @return list<array{title: string, fields: list<array<string, mixed>>}>
     */
    public static function sections(string $page): array {
        $placeholders = __('Variables :', 'ainatrix') . ' ' . implode(' ', MessageTemplate::PLACEHOLDERS);

        return match ($page) {
            'facebook' => [
                [
                    'title' => __('Application Meta', 'ainatrix'),
                    'fields' => [
                        self::text('app_id', __('App ID', 'ainatrix'), ['placeholder' => '1234567890123456']),
                        self::env('APP_SECRET', __('App Secret', 'ainatrix')),
                        [
                            'key'     => 'api_version',
                            'type'    => 'select',
                            'label'   => __('Version de l’API Graph', 'ainatrix'),
                            'options' => ['v21.0' => 'v21.0', 'v22.0' => 'v22.0', 'v23.0' => 'v23.0'],
                            'default' => 'v22.0',
                        ],
                        self::text('graph_url', __('URL de base de l’API Graph', 'ainatrix'), [
                            'default'     => 'https://graph.facebook.com',
                            'hint'        => __('Repli si absent du .env.', 'ainatrix'),
                        ]),
                    ],
                ],
                [
                    'title' => __('Page de publication', 'ainatrix'),
                    'fields' => [
                        self::text('page_id', __('ID de la page', 'ainatrix'), ['placeholder' => '987654321098765']),
                        self::env('PAGE_TOKEN', __('Page Access Token', 'ainatrix'), __('Token longue durée, généré après connexion de la page.', 'ainatrix')),
                    ],
                ],
                [
                    'title' => __('Comportement', 'ainatrix'),
                    'fields' => [
                        self::checkbox('auto_publish', __('Publication automatique', 'ainatrix'), __('Publier dès qu’un article passe en « publié ».', 'ainatrix')),
                        self::checkbox('link_first', __('Lien en priorité', 'ainatrix'), __('Placer le permalien en tête du message plutôt qu’en fin.', 'ainatrix'), true),
                        self::text('template', __('Gabarit de message', 'ainatrix'), ['hint' => $placeholders]),
                        self::number('timeout', __('Délai d’expiration (secondes)', 'ainatrix'), 30),
                    ],
                ],
            ],
            'linkedin' => [
                [
                    'title' => __('Application LinkedIn', 'ainatrix'),
                    'fields' => [
                        self::text('client_id', __('Client ID', 'ainatrix'), ['placeholder' => '86abcdef12345']),
                        self::env('CLIENT_SECRET', __('Client Secret', 'ainatrix')),
                        self::text('org_id', __('ID de l’organisation (page entreprise)', 'ainatrix'), [
                            'placeholder' => 'urn:li:organization:123456789',
                            'hint'        => __('Format URN. Vierge = publication sur le profil personnel.', 'ainatrix'),
                        ]),
                        self::text('api_url', __('URL de base de l’API', 'ainatrix'), ['default' => 'https://api.linkedin.com/v2']),
                    ],
                ],
                [
                    'title' => __('Comportement', 'ainatrix'),
                    'fields' => [
                        self::checkbox('auto_publish', __('Publication automatique', 'ainatrix'), __('Publier dès qu’un article passe en « publié ».', 'ainatrix')),
                        [
                            'key'     => 'visibility',
                            'type'    => 'select',
                            'label'   => __('Visibilité par défaut', 'ainatrix'),
                            'options' => ['PUBLIC' => __('Public', 'ainatrix'), 'CONNECTIONS' => __('Connexions uniquement', 'ainatrix')],
                            'default' => 'PUBLIC',
                        ],
                        self::text('template', __('Gabarit de message', 'ainatrix'), ['full' => true, 'hint' => $placeholders]),
                    ],
                ],
            ],
            'tiktok' => [
                [
                    'title' => __('Application TikTok', 'ainatrix'),
                    'fields' => [
                        self::text('client_key', __('Client Key', 'ainatrix'), ['placeholder' => 'aw1234567890abcdef']),
                        self::env('CLIENT_SECRET', __('Client Secret', 'ainatrix')),
                    ],
                ],
                [
                    'title' => __('Comportement', 'ainatrix'),
                    'fields' => [
                        self::checkbox('auto_publish', __('Publication automatique', 'ainatrix'), __('Publier dès qu’un article passe en « publié ».', 'ainatrix')),
                        [
                            'key'     => 'privacy',
                            'type'    => 'select',
                            'label'   => __('Confidentialité de la vidéo', 'ainatrix'),
                            'options' => [
                                'PUBLIC_TO_EVERYONE' => __('Publique', 'ainatrix'),
                                'FRIENDS_ONLY'       => __('Amis seulement', 'ainatrix'),
                                'SELF_ONLY'          => __('Privée (auto-postée)', 'ainatrix'),
                            ],
                            'default' => 'PUBLIC_TO_EVERYONE',
                        ],
                        self::text('default_hashtags', __('Hashtags par défaut', 'ainatrix'), ['placeholder' => '#actu #tech', 'full' => true]),
                    ],
                ],
            ],
            'instagram' => [
                [
                    'title' => __('Compte professionnel', 'ainatrix'),
                    'fields' => [
                        self::text('ig_user_id', __('ID du compte Instagram Business', 'ainatrix'), ['placeholder' => '1784xxxxxxxxxxx']),
                        self::text('fb_page_id', __('Page Facebook liée', 'ainatrix'), ['placeholder' => 'Ma Page — 987654321098765']),
                        self::env('PAGE_TOKEN', __('Page Access Token', 'ainatrix'), __('Partagé avec Facebook : même app Meta, même page.', 'ainatrix')),
                    ],
                ],
                [
                    'title' => __('Comportement', 'ainatrix'),
                    'fields' => [
                        self::checkbox('auto_publish', __('Publication automatique', 'ainatrix'), __('Publier dès qu’un article passe en « publié ».', 'ainatrix')),
                        self::checkbox('carousel', __('Carrousels', 'ainatrix'), __('Publier les images de l’article en carrousel.', 'ainatrix')),
                        self::text('template', __('Légende', 'ainatrix'), [
                            'full' => true,
                            'hint' => __('Les liens cliquables ne sont pas supportés en légende : {lien_court} pointe vers le lien en bio.', 'ainatrix'),
                        ]),
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * Champs persistés d'une page, à plat (clé => spécification), sans les champs .env.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function persistedFields(string $page): array {
        $fields = [];
        foreach (self::sections($page) as $section) {
            foreach ($section['fields'] as $field) {
                if (($field['type'] ?? 'text') !== 'env' && isset($field['key'])) {
                    $fields[$field['key']] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Spécification d'un champ texte.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function text(string $key, string $label, array $args = []): array {
        return ['key' => $key, 'type' => 'text', 'label' => $label] + $args;
    }

    /**
     * Spécification d'un champ numérique.
     *
     * @return array<string, mixed>
     */
    private static function number(string $key, string $label, int $default): array {
        return ['key' => $key, 'type' => 'number', 'label' => $label, 'default' => $default];
    }

    /**
     * Spécification d'une case à cocher.
     *
     * @return array<string, mixed>
     */
    private static function checkbox(string $key, string $label, string $sub, bool $default = false): array {
        return ['key' => $key, 'type' => 'checkbox', 'label' => $label, 'sub' => $sub, 'default' => $default];
    }

    /**
     * Spécification d'un champ .env (lecture seule : statut, jamais de valeur).
     */
    private static function env(string $suffix, string $label, string $hint = ''): array {
        return ['type' => 'env', 'suffix' => $suffix, 'label' => $label, 'hint' => $hint];
    }

    /**
     * Description d'en-tête d'une page (HTML autorisé : <code>).
     */
    public static function description(string $page): string {
        $text = match ($page) {
            'facebook'  => sprintf(
                __('Publication sur les pages via l’API Graph. L’app doit avoir les permissions %1$s, %2$s.', 'ainatrix'),
                '<code>pages_manage_posts</code>',
                '<code>pages_read_engagement</code>'
            ),
            'linkedin'  => sprintf(
                __('Publication via l’API Posts. L’app doit avoir %1$s (page entreprise) ou %2$s (profil) approuvés.', 'ainatrix'),
                '<code>Community Management API</code>',
                '<code>Share on LinkedIn</code>'
            ),
            'tiktok'    => __('Publication vidéo via l’API Content Posting. Nécessite une app approuvée et un compte Business.', 'ainatrix'),
            'instagram' => __('Publication via l’API Instagram Graph — compte professionnel rattaché à une page Facebook.', 'ainatrix'),
            default     => '',
        };

        return wp_kses($text, ['code' => []]);
    }

    /**
     * Nom complet de la variable .env d'un champ env d'une page.
     */
    public static function envName(string $page, array $field): string {
        return Secrets::envName($page, (string) $field['suffix']);
    }
}
