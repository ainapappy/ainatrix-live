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
$ariaLabel = isset($context['ariaLabel']) ? (string) $context['ariaLabel'] : '';
$usernameLabel = isset($context['usernameLabel']) ? (string) $context['usernameLabel'] : '';
$passwordLabel = isset($context['passwordLabel']) ? (string) $context['passwordLabel'] : '';
$submitLabel = isset($context['submitLabel']) ? (string) $context['submitLabel'] : '';
$lostPassword = isset($context['lostPassword']) ? (string) $context['lostPassword'] : '';
$backToSite = isset($context['backToSite']) ? (string) $context['backToSite'] : '';
$loginUrl = isset($context['loginUrl']) ? (string) $context['loginUrl'] : wp_login_url();
$redirectTo = isset($context['redirectTo']) ? (string) $context['redirectTo'] : admin_url();
$remember = !empty($context['remember']);
$username = isset($context['username']) ? (string) $context['username'] : '';
$nonceFieldName = isset($context['nonceFieldName']) ? (string) $context['nonceFieldName'] : 'aix_live_login_nonce';
$nonceValue = isset($context['nonceValue']) ? (string) $context['nonceValue'] : '';
?>
<main class="aix-login-shell" data-aix-login-custom>
    <h2 class="aix-login-shell__brand"><?php echo esc_html($brand); ?></h2>
    <section class="aix-login-card" aria-label="<?php echo esc_attr($ariaLabel); ?>">
        <form class="aix-login-card__form" id="aix_live_login_form" name="loginform" action="<?php echo esc_url($loginUrl); ?>" method="post">
            <p class="aix-login-card__field">
                <label for="aix_user_login"><?php echo esc_html($usernameLabel); ?></label>
                <input id="aix_user_login" name="log" type="text" autocomplete="username" value="<?php echo esc_attr($username); ?>" required>
            </p>
            <p class="aix-login-card__field">
                <label for="aix_user_pass"><?php echo esc_html($passwordLabel); ?></label>
                <input id="aix_user_pass" name="pwd" type="password" autocomplete="current-password" required>
            </p>
            <p class="forgetmenot aix-login-card__remember">
                <label for="aix_rememberme">
                    <input id="aix_rememberme" name="rememberme" type="checkbox" value="forever"<?php checked($remember); ?>>
                    <?php echo esc_html__('Se souvenir de moi', 'ainatrix'); ?>
                </label>
            </p>
            <p class="submit">
                <button class="submit aix-login-card__submit" type="submit" name="wp-submit" value="<?php echo esc_attr($submitLabel); ?>">
                    <?php echo esc_html($submitLabel); ?>
                </button>
            </p>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo); ?>">
            <input type="hidden" name="testcookie" value="1">
            <?php if ($nonceValue !== '') : ?>
                <input type="hidden" name="<?php echo esc_attr($nonceFieldName); ?>" value="<?php echo esc_attr($nonceValue); ?>">
            <?php endif; ?>
        </form>
    </section>
    <p class="aix-login-shell__meta">
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php echo esc_html($lostPassword); ?></a>
        ·
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($backToSite); ?></a>
    </p>
</main>
