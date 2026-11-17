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

  $forms.on('submit', function () {
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
