/**
 * Page « Réseaux sociaux » : onglets, affichage des champs .env, toast,
 * enregistrement et test de connexion (AJAX).
 */
jQuery(function ($) {
  'use strict';

  var cfg = window.aixLiveSettings || {};
  var i18n = cfg.i18n || {};
  var STORAGE_KEY = 'aix-live-settings-tab';

  /* ── Toast ── */
  var $toast = null;
  var toastTimer = null;
  function toast(msg, ok) {
    if (!$toast) { $toast = $('#aix-toast'); }
    if (!$toast.length) { return; }
    $toast.text(msg)
      .css('background', ok === true ? '#00a32a' : (ok === false ? '#d63638' : '#1d2327'))
      .addClass('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { $toast.removeClass('show'); }, 2500);
  }

  /* ── Onglets ── */
  function activate(name) {
    $('#aix-tabs .tab').each(function () {
      $(this).attr('aria-selected', String($(this).data('panel') === name));
    });
    $('.aix-settings .panel').each(function () {
      var active = $(this).data('provider') === name;
      $(this).toggleClass('active', active).attr('hidden', active ? null : 'hidden');
    });
  }

  $('#aix-tabs').on('click', '.tab', function () {
    var name = String($(this).data('panel'));
    activate(name);
    try { localStorage.setItem(STORAGE_KEY, name); } catch (e) { /* sans stockage */ }
  });

  try {
    var saved = localStorage.getItem(STORAGE_KEY);
    if (saved && $('#panel-' + saved).length) { activate(saved); }
  } catch (e) { /* ignorer */ }

  /* ── Afficher / masquer les champs .env ── */
  $(document).on('click', '.toggle-secret', function () {
    var $input = $('#' + $(this).data('target'));
    var show = $input.attr('type') === 'password';
    $input.attr('type', show ? 'text' : 'password');
    $(this).text(show ? 'Masquer' : 'Afficher');
  });

  /* ── Confirmation d'enregistrement (retour de admin-post) ── */
  if (cfg.updated === '1') {
    toast(i18n.saved || 'Réglages enregistrés.', true);
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', window.location.pathname + window.location.search.replace(/[?&]updated=1/, ''));
    }
  }

  /* ── Tester la connexion (réseau affiché) ── */
  $('#btn-test').on('click', function () {
    var $panel = $('.aix-settings .panel.active');
    var provider = String($panel.data('provider') || '');
    if (!provider) { return; }

    var $btn = $(this).prop('disabled', true);
    toast(i18n.testing || 'Test de connexion en cours…');

    $.post(cfg.ajaxUrl, { action: 'aix_live_test_connection', nonce: cfg.nonce, provider: provider })
      .done(function (res) {
        var ok = !!(res && res.success);
        var message = (res && res.data && res.data.message) || '';
        if (!ok && !message) { message = i18n.missingFields || 'Configuration incomplète.'; }
        toast(message, ok);
        $panel.find('[data-pill]')
          .attr('class', 'status ' + (ok ? 'ok' : 'todo'))
          .text(ok ? (i18n.connected || 'Connecté') : (i18n.notConnected || 'Non configuré'));
      })
      .fail(function () {
        toast(i18n.networkError || 'Erreur réseau : test impossible.', false);
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });
});
