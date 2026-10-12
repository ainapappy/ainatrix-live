<?php
/**
 * Rendu de la page d'administration « Réseaux sociaux ».
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\SocialSettings;

use AinatrixLive\Providers\BrandIcons;
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

    public function __construct(private readonly ProviderRegistry $registry) {}

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
                            <span class="ico"><?php echo BrandIcons::icon((string) $key); ?></span>
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
                    <h2><span class="ico"><?php echo BrandIcons::icon((string) $key); ?></span> <?php echo esc_html($provider->label()); ?></h2>
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
