/**
 * Micro-interactions discrètes pour wp-login.php.
 */
jQuery(function ($) {
  'use strict';

  var $body = $('body.login');
  var $forms = $('#loginform, #lostpasswordform, #registerform, #resetpassform');
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
});
