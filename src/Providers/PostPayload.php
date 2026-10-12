<?php
/**
 * Données d'un article à publier, dérivées d'un WP_Post.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DTO immuable transmis aux providers. Construit par la feature appelante
 * (file de publication, action manuelle) via fromPost() — les providers ne
 * connaissent jamais l'objet WP_Post lui-même.
 */
final class PostPayload {

    /**
     * @param int    $postId   ID de l'article source.
     * @param string $title    Titre (texte brut).
     * @param string $excerpt  Extrait (texte brut, généré depuis le contenu si vide).
     * @param string $permalink Permaliens canonique.
     * @param string $shortlink Lien court (wp_get_shortlink), pour {lien_court}.
     * @param string $hashtags Hashtags séparés par des espaces (déduits des tags si vides).
     * @param string $imageUrl URL absolue de l'image mise en avant, '' si aucune.
     */
    public function __construct(
        public readonly int $postId,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly string $permalink,
        public readonly string $shortlink,
        public readonly string $hashtags,
        public readonly string $imageUrl,
    ) {}

    /**
     * Construit le payload depuis un article WordPress.
     *
     * @param int|\WP_Post $post     Article (ID ou objet).
     * @param string       $hashtags Hashtags imposés par l'appelant, sinon déduits des tags de l'article.
     * @throws ProviderException Si l'article est introuvable.
     */
    public static function fromPost(int|\WP_Post $post, string $hashtags = ''): self {
        $post = get_post($post);
        if (!$post instanceof \WP_Post) {
            throw new ProviderException(__('Article introuvable.', 'ainatrix'));
        }

        $excerpt = trim((string) get_the_excerpt($post));
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 55, '…');
        }

        if ($hashtags === '') {
            $hashtags = self::hashtagsFromTags($post->ID);
        }

        return new self(
            $post->ID,
            (string) get_the_title($post),
            $excerpt,
            (string) get_permalink($post),
            (string) wp_get_shortlink($post->ID),
            $hashtags,
            (string) get_the_post_thumbnail_url($post, 'large'),
        );
    }

    /**
     * Assemble les tags de l'article en hashtags (#tag, séparés par un espace).
     */
    private static function hashtagsFromTags(int $postId): string {
        $tags = get_the_tags($postId);
        if (!is_array($tags) || $tags === []) {
            return '';
        }

        $hashtags = [];
        foreach ($tags as $tag) {
            $slug = sanitize_title((string) ($tag->slug ?? ''));
            if ($slug !== '') {
                $hashtags[] = '#' . $slug;
            }
        }

        return implode(' ', $hashtags);
    }
}
