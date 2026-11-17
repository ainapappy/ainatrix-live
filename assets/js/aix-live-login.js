/**
 * Micro-interactions discrètes pour wp-login.php.
 */
jQuery(function ($) {
  'use strict';

  var $body = $('body.login');
  var $forms = $('#aix_live_login_form, #loginform, #lostpasswordform, #registerform, #resetpassform');
  var storageKey = 'aix_live_login_theme';
  if (!$forms.length) { return; }

  $forms.find('input.input, input[type="text"], input[type="password"], input[type="email"]')
    .on('focus', function () {
      $(this).closest('p').addClass('is-focused');
    })
    .on('blur', function () {
      $(this).closest('p').removeClass('is-focused');
    });

  // Verrou de soumission unique (hotfix 2026-11-17 « page blanche après
  // login ») : pendant la fenêtre async de grecaptcha.execute (~2-3 s), un
  // double-clic ou une seconde pression sur Entrée déclenchait une seconde
  // soumission ; le navigateur avortait le premier POST en vol (réponse vide
  // rendue à l'écran, URL figée sur wp-login.php) pendant que le second
  // réussissait côté serveur. Premier submit : verrou + bouton désactivé ;
  // soumissions suivantes : avortées. submitNative() du runner reCAPTCHA
  // contourne l'événement submit et n'est pas affectée.
  $forms.on('submit', function (event) {
    var $form = $(this);
    if ($form.data('aix-submitted')) {
      event.preventDefault();
      return;
    }
    $form.data('aix-submitted', true);
    $form.find('button[type="submit"], input[type="submit"]').prop('disabled', true);
    $body.addClass('is-submitting');
  });

  var $error = $('#login_error');
  if ($error.length) {
    $error.css({ transform: 'translateY(-2px)', transition: 'transform .24s ease' });
    setTimeout(function () {
      $error.css({ transform: 'translateY(0)' });
    }, 140);
  }

  var $switch = $('[data-aix-theme-switch]');
  if (!$switch.length) { return; }

  var applyTheme = function (theme) {
    var isLight = theme === 'light';
    $body.toggleClass('aix-theme-light', isLight);
    $switch.attr('aria-pressed', isLight ? 'true' : 'false');
  };

  var getStoredTheme = function () {
    try {
      return window.localStorage.getItem(storageKey);
    } catch (e) {
      return null;
    }
  };

  var setStoredTheme = function (theme) {
    try {
      window.localStorage.setItem(storageKey, theme);
    } catch (e) {
      // No-op: stockage local indisponible.
    }
  };

  var savedTheme = getStoredTheme();
  applyTheme(savedTheme === 'light' ? 'light' : 'dark');

  $switch.on('click', function () {
    var nextTheme = $body.hasClass('aix-theme-light') ? 'dark' : 'light';
    applyTheme(nextTheme);
    setStoredTheme(nextTheme);
  });
});
