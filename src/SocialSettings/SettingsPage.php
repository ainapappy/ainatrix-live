<?php
/**
 * Rendu de la page d'administration « Réseaux sociaux ».
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\SocialSettings;

use AinatrixLive\Providers\ProviderConfig;
use AinatrixLive\Providers\ProviderInterface;
use AinatrixLive\Providers\ProviderRegistry;
use AinatrixLive\Providers\SecretStore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Traduit la maquette (.knowledge/design/social-networks-config.html) en
 * markup WordPress : onglets par réseau, champs du schéma, statut de
 * configuration (badge + pastille) et boutons d'action.
 *
 * Adaptation de sécurité par rapport à la maquette : les champs secrets sont
 * soumis mais jamais re-rendus avec leur valeur — l'input affiche uniquement
 * un masque, champ vide = valeur conservée. Stockage : option chiffrée
 * (SecretStore), repli .env.
 */
final class SettingsPage {

    /**
     * Icônes officielles des réseaux (chemins SVG 24×24, format Simple Icons)
     * et couleurs de marque.
     *
     * @var array<string, array{color: string, path: string}>
     */
    private const BRAND_ICONS = [
        'facebook' => [
            'color' => '#1877F2',
            'path'  => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
        ],
        'linkedin' => [
            'color' => '#0A66C2',
            'path'  => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z',
        ],
        'tiktok' => [
            'color' => '#000000',
            'path'  => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
        ],
        'instagram' => [
            // Dégradé officiel Instagram (au lieu d'une couleur pleine).
            'color' => 'url(#aix-ig-gradient)',
            'path'  => 'M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z',
        ],
    ];

    public function __construct(private readonly ProviderRegistry $registry) {}

    /**
     * Icône SVG de marque d'un réseau (constante interne, aucune entrée utilisateur).
     */
    private function icon(string $key): string {
        $brand = self::BRAND_ICONS[$key] ?? null;
        if ($brand === null) {
            return '';
        }

        $svg = '<svg class="brand-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">';
        if ($key === 'instagram') {
            $svg .= '<defs><linearGradient id="aix-ig-gradient" x1="0" y1="1" x2="1" y2="0">'
                . '<stop offset="0%" stop-color="#FEDA75"/><stop offset="25%" stop-color="#FA7E1E"/>'
                . '<stop offset="50%" stop-color="#D62976"/><stop offset="75%" stop-color="#962FBF"/>'
                . '<stop offset="100%" stop-color="#4F5BD5"/></linearGradient></defs>';
        }
        $svg .= sprintf('<path fill="%1$s" d="%2$s"/></svg>', $brand['color'], $brand['path']);

        return $svg;
    }

    /**
     * Affiche la page complète (formulaire + onglets + panneaux).
     */
    public function render(): void {
        $providers = $this->registry->all();
        $first = SettingsSchema::PAGES[0];
        ?>
        <div class="wrap aix-settings">

            <h1 class="page-title">
                <span class="logo"><?php esc_html_e('ainatrix live', 'ainatrix'); ?></span>
                — <?php esc_html_e('Réglages réseaux sociaux', 'ainatrix'); ?>
            </h1>
            <p class="page-sub">
                <?php esc_html_e('Configuration des connexions de publication.', 'ainatrix'); ?>
                <?php esc_html_e('Les secrets sont stockés dans le fichier .env du plugin — jamais en base de données.', 'ainatrix'); ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="aix_live_save_settings">
                <?php wp_nonce_field('aix_live_save_settings', 'aix_live_nonce'); ?>

                <div class="tabs" role="tablist" id="aix-tabs">
                    <?php foreach ($providers as $key => $provider) : ?>
                        <button type="button" class="tab" role="tab" data-panel="<?php echo esc_attr((string) $key); ?>" aria-selected="<?php echo $key === $first ? 'true' : 'false'; ?>">
                            <span class="ico"><?php echo $this->icon((string) $key); ?></span>
                            <?php echo esc_html($provider->label()); ?>
                            <span class="badge <?php echo $provider->isConfigured() ? 'on' : ''; ?>">
                                <?php echo $provider->isConfigured() ? esc_html__('Configuré', 'ainatrix') : esc_html__('À faire', 'ainatrix'); ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($providers as $key => $provider) : ?>
                    <?php $this->renderPanel((string) $key, $provider, $key === $first); ?>
                <?php endforeach; ?>

                <div class="actions">
                    <button type="button" class="btn" id="btn-test"><?php esc_html_e('Tester la connexion', 'ainatrix'); ?></button>
                    <button type="submit" class="btn primary"><?php esc_html_e('Enregistrer', 'ainatrix'); ?></button>
                </div>
            </form>
        </div>

        <div id="toast" role="status"></div>
        <?php
    }

    /**
     * Panneau d'un réseau : en-tête, note .env, sections de champs.
     */
    private function renderPanel(string $key, ProviderInterface $provider, bool $active): void {
        $config = ProviderConfig::load($key, $provider->defaultSettings());
        ?>
        <section class="panel<?php echo $active ? ' active' : ''; ?>" id="panel-<?php echo esc_attr($key); ?>" role="tabpanel" data-provider="<?php echo esc_attr($key); ?>"<?php echo $active ? '' : ' hidden'; ?>>
            <div class="panel-head">
                <div>
                    <h2><span class="ico"><?php echo $this->icon((string) $key); ?></span> <?php echo esc_html($provider->label()); ?></h2>
                    <p><?php echo wp_kses_post(SettingsSchema::description($key)); ?></p>
                </div>
                <span class="status <?php echo $provider->isConfigured() ? 'ok' : 'todo'; ?>" data-pill>
                    <?php echo $provider->isConfigured() ? esc_html__('Configuré', 'ainatrix') : esc_html__('Non configuré', 'ainatrix'); ?>
                </span>
            </div>

            <div class="env-note">
                🔒 <?php esc_html_e('Les secrets saisis ici sont chiffrés en base de données. Le fichier .env du plugin reste un repli pour le développement local.', 'ainatrix'); ?>
            </div>

            <?php foreach (SettingsSchema::sections($key) as $section) : ?>
                <div class="section">
                    <h3 class="section-title"><?php echo esc_html($section['title']); ?></h3>
                    <div class="grid">
                        <?php foreach ($section['fields'] as $field) : ?>
                            <?php $this->renderField($key, $config, $field); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    /**
     * Un champ, selon son type (spécification du schéma).
     *
     * @param ProviderConfig       $config Configuration effective du réseau.
     * @param array<string, mixed> $field  Spécification du champ.
     */
    private function renderField(string $key, ProviderConfig $config, array $field): void {
        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'secret') {
            $this->renderSecretField($key, $field);

            return;
        }

        $fieldKey = (string) $field['key'];
        $name = sprintf('aix_live[%1$s][%2$s]', $key, $fieldKey);
        $id = sprintf('%1$s-%2$s', $key, strtr($fieldKey, '_', '-'));
        $classes = ['field'];
        if (!empty($field['full'])) {
            $classes[] = 'full';
        }
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($type === 'checkbox') : ?>
                <label class="check" for="<?php echo esc_attr($id); ?>">
                    <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="1"<?php checked($config->bool($fieldKey, (bool) ($field['default'] ?? false))); ?>>
                    <span class="lbl">
                        <?php echo esc_html((string) $field['label']); ?>
                        <span class="sub"><?php echo esc_html((string) ($field['sub'] ?? '')); ?></span>
                    </span>
                </label>
            <?php else : ?>
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html((string) $field['label']); ?></label>
                <?php if ($type === 'select') : ?>
                    <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                        <?php foreach ((array) $field['options'] as $value => $label) : ?>
                            <option value="<?php echo esc_attr((string) $value); ?>"<?php selected($config->string($fieldKey, (string) ($field['default'] ?? '')), (string) $value); ?>>
                                <?php echo esc_html((string) $label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="<?php echo esc_attr($type === 'number' ? 'text' : 'text'); ?>"
                           id="<?php echo esc_attr($id); ?>"
                           name="<?php echo esc_attr($name); ?>"
                           value="<?php echo esc_attr($type === 'number' ? (string) $config->int($fieldKey, (int) ($field['default'] ?? 0)) : $config->string($fieldKey, (string) ($field['default'] ?? ''))); ?>"
                           <?php echo isset($field['placeholder']) ? 'placeholder="' . esc_attr((string) $field['placeholder']) . '"' : ''; ?>
                           <?php echo $type === 'number' ? 'inputmode="numeric"' : ''; ?>>
                <?php endif; ?>
                <?php if (isset($field['hint'])) : ?>
                    <span class="hint"><?php echo esc_html((string) $field['hint']); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Champ secret : saisie password, jamais de valeur re-rendue. Stockage
     * chiffré en base (SecretStore), repli .env. Champ vide = valeur conservée.
     *
     * @param array<string, mixed> $field
     */
    private function renderSecretField(string $page, array $field): void {
        $suffix = (string) $field['suffix'];
        $set = SecretStore::has($page, $suffix);
        $envName = SettingsSchema::envName($page, $field);
        $id = 'secret-' . strtolower(strtr($envName, '_', '-'));
        ?>
        <div class="field">
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html((string) $field['label']); ?>
            </label>
            <div class="secret-row">
                <input type="password" id="<?php echo esc_attr($id); ?>"
                       name="<?php echo esc_attr(sprintf('aix_live[%1$s][%2$s]', $page, $suffix)); ?>"
                       value="" autocomplete="off"
                       placeholder="<?php echo $set ? esc_attr__('••••••••••••', 'ainatrix') : ''; ?>"
                       <?php echo $set ? '' : ' class="missing"'; ?>>
                <button type="button" class="toggle-secret" data-target="<?php echo esc_attr($id); ?>" aria-label="<?php esc_attr_e('Afficher ou masquer', 'ainatrix'); ?>">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                </button>
            </div>
            <span class="hint">
                <span class="env-tag"><?php echo esc_html($envName); ?></span> —
                <?php echo $set
                    ? esc_html__('enregistré, chiffré en base (laisser vide pour conserver)', 'ainatrix')
                    : esc_html__('non enregistré', 'ainatrix'); ?>
                <?php if (($field['hint'] ?? '') !== '') : ?>
                    · <?php echo esc_html((string) $field['hint']); ?>
                <?php endif; ?>
            </span>
        </div>
        <?php
    }
}
