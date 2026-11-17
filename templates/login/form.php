<?php
/**
 * Bloc marketing affiché au-dessus du formulaire de connexion natif.
 *
 * @package ainatrix
 */

if (!defined('ABSPATH')) {
    exit;
}

$brand = isset($context['brand']) ? (string) $context['brand'] : '';
$headline = isset($context['headline']) ? (string) $context['headline'] : '';
$description = isset($context['description']) ? (string) $context['description'] : '';
$badge = isset($context['badge']) ? (string) $context['badge'] : '';
?>
<section class="aix-login-template" aria-label="<?php echo esc_attr($headline); ?>">
    <p class="aix-login-template__badge"><?php echo esc_html($badge); ?></p>
    <h2 class="aix-login-template__title"><?php echo esc_html($brand); ?></h2>
    <p class="aix-login-template__headline"><?php echo esc_html($headline); ?></p>
    <p class="aix-login-template__description"><?php echo esc_html($description); ?></p>
</section>
