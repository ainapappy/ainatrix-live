/**
 * reCAPTCHA v3 discret : sur la soumission de chaque formulaire portant un
 * champ caché data-aix-recaptcha-site-key, exécute grecaptcha.execute,
 * injecte le token dans le champ puis soumet en natif. Aucune chaîne UI : en
 * cas d'échec côté serveur, le message générique est rendu par WordPress.
 */
jQuery(function ($) {
  'use strict';

  // Cible tout formulaire contenant le champ caché (réutilisable au-delà du login).
  var $forms = $('form').has('input[data-aix-recaptcha-site-key]');
  if (!$forms.length) { return; }

  var fieldData = function ($field) {
    return {
      siteKey: String($field.attr('data-aix-recaptcha-site-key') || ''),
      action: String($field.attr('data-aix-recaptcha-action') || 'submit')
    };
  };

  // Soumission DOM native : ne repasse pas par les handlers jQuery (pas de reboucle).
  var submitNative = function (form) {
    if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
      HTMLFormElement.prototype.submit.call(form);
      return;
    }
    form.submit();
  };

  // Soumet sans token : le serveur répondra avec le message d'erreur générique.
  var submitWithoutToken = function ($form, $field) {
    $field.val('');
    submitNative($form[0]);
  };

  $forms.on('submit', function (event) {
    var $form = $(this);
    var $field = $form.find('input[data-aix-recaptcha-site-key]').first();
    if (!$field.length) { return; }

    var data = fieldData($field);
    if (!data.siteKey) { return; }

    // API Google indisponible (bloquée, CSP, offline) : soumettre sans token
    // plutôt que figer l'écran.
    if (typeof window.grecaptcha === 'undefined' || typeof window.grecaptcha.execute !== 'function') {
      return;
    }

    event.preventDefault();

    window.grecaptcha.ready(function () {
      window.grecaptcha
        .execute(data.siteKey, { action: data.action })
        .then(function (token) {
          if (token) {
            $field.val(token);
            submitNative($form[0]);
          } else {
            submitWithoutToken($form, $field);
          }
        })
        .catch(function () {
          submitWithoutToken($form, $field);
        });
    });
  });
});
