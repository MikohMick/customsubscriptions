/* Custom Memberships — Admin JS */
(function ($) {
  'use strict';

  var nonce = cmAdmin.nonce;

  // ── Modal helpers ─────────────────────────────────────────────────

  function openModal($modal) {
    $modal.show();
    $modal.find('input:visible').first().focus();
    $(document).on('keydown.cmmodal', function (e) {
      if (e.key === 'Escape') closeModal($modal);
    });
  }

  function closeModal($modal) {
    $modal.hide();
    $(document).off('keydown.cmmodal');
  }

  function showModalNotice($modal, msg, type) {
    var $n = $modal.find('.cm-modal-notice');
    $n.removeClass('is-error is-success').addClass('is-' + type).text(msg).show();
  }

  function clearModalNotice($modal) {
    $modal.find('.cm-modal-notice').hide().text('');
  }

  $(document).on('click', '.cm-modal-close', function () {
    closeModal($(this).closest('.cm-modal'));
  });

  // ── Packages page ─────────────────────────────────────────────────

  var $pkgModal = $('#cm-package-modal');

  // Open for add.
  $(document).on('click', '.cm-btn-add-package', function () {
    $('#cm-pkg-id').val('0');
    $('#cm-package-form')[0].reset();
    $('#cm-pkg-active').prop('checked', true);
    $('#cm-package-modal-title').text('Add Package');
    clearModalNotice($pkgModal);
    openModal($pkgModal);
  });

  // Open for edit.
  $(document).on('click', '.cm-btn-edit-package', function () {
    var pkg = JSON.parse($(this).data('package'));
    $('#cm-pkg-id').val(pkg.id);
    $('#cm-pkg-name').val(pkg.name);
    $('#cm-pkg-price').val(pkg.price);
    $('#cm-pkg-sessions').val(pkg.sessions);
    $('#cm-pkg-order').val(pkg.sort_order);
    $('#cm-pkg-desc').val(pkg.description);
    $('#cm-pkg-active').prop('checked', parseInt(pkg.active) === 1);
    $('#cm-package-modal-title').text('Edit Package');
    clearModalNotice($pkgModal);
    openModal($pkgModal);
  });

  // Save package.
  $('#cm-package-form').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');
    $.post(cmAdmin.ajaxUrl, {
      action:      'cm_admin_save_package',
      nonce:       nonce,
      id:          $('#cm-pkg-id').val(),
      name:        $('#cm-pkg-name').val(),
      price:       $('#cm-pkg-price').val(),
      sessions:    $('#cm-pkg-sessions').val(),
      sort_order:  $('#cm-pkg-order').val(),
      description: $('#cm-pkg-desc').val(),
      active:      $('#cm-pkg-active').is(':checked') ? 1 : 0,
    }, function (res) {
      if (res.success) {
        showModalNotice($pkgModal, cmAdmin.i18n.saved, 'success');
        setTimeout(function () { location.reload(); }, 800);
      } else {
        showModalNotice($pkgModal, res.data.message || cmAdmin.i18n.error, 'error');
      }
    }).fail(function () {
      showModalNotice($pkgModal, cmAdmin.i18n.error, 'error');
    }).always(function () {
      $btn.prop('disabled', false).text('Save Package');
    });
  });

  // Delete package.
  $(document).on('click', '.cm-btn-delete-package', function () {
    if (!confirm(cmAdmin.i18n.confirm_delete)) return;
    var id = $(this).data('id');
    $.post(cmAdmin.ajaxUrl, { action: 'cm_admin_delete_package', nonce: nonce, id: id }, function (res) {
      if (res.success) {
        $('#cm-package-row-' + id).fadeOut(300, function () { $(this).remove(); });
      } else {
        alert(res.data.message || cmAdmin.i18n.error);
      }
    });
  });

  // ── Members page ──────────────────────────────────────────────────

  var $mbrModal = $('#cm-member-modal');

  // Open for add.
  $(document).on('click', '.cm-btn-add-member', function () {
    $('#cm-member-id').val('0');
    $('#cm-member-form')[0].reset();
    $('#cm-member-modal-title').text('Add Member');
    $mbrModal.find('.cm-field--new-only').show();
    $mbrModal.find('.cm-field--edit-only').hide();
    clearModalNotice($mbrModal);
    openModal($mbrModal);
  });

  // Open for edit.
  $(document).on('click', '.cm-btn-edit-member', function () {
    var m = JSON.parse($(this).data('member'));
    $('#cm-member-id').val(m.id);
    $('#cm-m-name').val(m.name);
    $('#cm-m-email').val(m.email);
    $('#cm-m-phone').val(m.phone);
    $('#cm-m-location').val(m.location);
    $('#cm-m-sessions').val(m.sessions_remaining);
    $('#cm-m-status').val(m.status);
    $('#cm-m-notes').val(m.notes || '');
    $('#cm-member-modal-title').text('Edit Member');
    $mbrModal.find('.cm-field--new-only').hide();
    $mbrModal.find('.cm-field--edit-only').show();
    clearModalNotice($mbrModal);
    openModal($mbrModal);
  });

  // Save member.
  $('#cm-member-form').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');
    var id   = $('#cm-member-id').val();
    $.post(cmAdmin.ajaxUrl, {
      action:             'cm_admin_save_member',
      nonce:              nonce,
      id:                 id,
      name:               $('#cm-m-name').val(),
      email:              $('#cm-m-email').val(),
      phone:              $('#cm-m-phone').val(),
      location:           $('#cm-m-location').val(),
      package_id:         $('#cm-m-package').val(),
      sessions_remaining: $('#cm-m-sessions').val(),
      status:             $('#cm-m-status').val(),
      notes:              $('#cm-m-notes').val(),
    }, function (res) {
      if (res.success) {
        showModalNotice($mbrModal, cmAdmin.i18n.saved, 'success');
        setTimeout(function () { location.reload(); }, 800);
      } else {
        showModalNotice($mbrModal, res.data.message || cmAdmin.i18n.error, 'error');
      }
    }).fail(function () {
      showModalNotice($mbrModal, cmAdmin.i18n.error, 'error');
    }).always(function () {
      $btn.prop('disabled', false).text('Save Member');
    });
  });

  // Delete member.
  $(document).on('click', '.cm-btn-delete-member', function () {
    if (!confirm(cmAdmin.i18n.confirm_delete)) return;
    var id = $(this).data('id');
    $.post(cmAdmin.ajaxUrl, { action: 'cm_admin_delete_member', nonce: nonce, id: id }, function (res) {
      if (res.success) {
        $('#cm-member-row-' + id).fadeOut(300, function () { $(this).remove(); });
      } else {
        alert(res.data.message || cmAdmin.i18n.error);
      }
    });
  });

  // Use session (–1 button).
  $(document).on('click', '.cm-btn-use-session', function () {
    var id  = $(this).data('id');
    var $btn = $(this).prop('disabled', true);
    $.post(cmAdmin.ajaxUrl, { action: 'cm_admin_use_session', nonce: nonce, id: id, amount: 1 }, function (res) {
      if (res.success) {
        var m   = res.data.member;
        var $td = $btn.closest('td');
        var unlimited = parseInt(m.sessions_total) === 0;
        if (!unlimited) {
          var zero = parseInt(m.sessions_remaining) === 0;
          $td.find('.cm-sessions-remaining')
            .text(m.sessions_remaining + ' / ' + m.sessions_total)
            .toggleClass('cm-sessions-zero', zero);
        }
        $btn.prop('disabled', false);

        // Flash row green briefly.
        $btn.closest('tr').css('background', '#e6f4ea');
        setTimeout(function () { $btn.closest('tr').css('background', ''); }, 1200);
      } else {
        alert(res.data.message || cmAdmin.i18n.error);
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      alert(cmAdmin.i18n.error);
      $btn.prop('disabled', false);
    });
  });

  // ── API Keys page ─────────────────────────────────────────────────

  var $keyModal    = $('#cm-key-modal');
  var $keyFormWrap = $('#cm-key-form-wrap');
  var $keyResWrap  = $('#cm-key-result-wrap');

  function openKeyModal() {
    $('#cm-key-form')[0].reset();
    $keyFormWrap.show();
    $keyResWrap.hide();
    clearModalNotice($keyModal);
    openModal($keyModal);
  }

  function closeKeyModal() {
    closeModal($keyModal);
  }

  $('#cm-btn-add-key').on('click', openKeyModal);
  $('#cm-key-modal-cancel, #cm-key-modal-close, #cm-key-modal-overlay').on('click', closeKeyModal);
  $('#cm-key-done-btn').on('click', function () { location.reload(); });

  $('#cm-key-form').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Generating…');
    $.ajax({
      url:         cmAdmin.ajaxUrl.replace('/admin-ajax.php', '') + '/wp-json/custom-memberships/v1/keys',
      method:      'POST',
      contentType: 'application/json',
      data:        JSON.stringify({ label: $('#cm-key-label').val(), permissions: $('#cm-key-perms').val() }),
      headers:     { 'X-WP-Nonce': cmAdmin.nonce },
      success: function (res) {
        $('#cm-generated-key').val(res.api_key);
        $keyFormWrap.hide();
        $keyResWrap.show();
      },
      error: function () {
        showModalNotice($keyModal, cmAdmin.i18n.error, 'error');
        $btn.prop('disabled', false).text('Generate Key');
      },
    });
  });

  // Copy key.
  $('#cm-copy-key').on('click', function () {
    var $input = $('#cm-generated-key');
    $input[0].select();
    document.execCommand('copy');
    $(this).text(cmAdmin.i18n.copy_success);
  });

  // Delete API key.
  $(document).on('click', '.cm-btn-delete-key', function () {
    if (!confirm(cmAdmin.i18n.confirm_delete)) return;
    var id = $(this).data('id');
    $.ajax({
      url:     cmAdmin.ajaxUrl.replace('/admin-ajax.php', '') + '/wp-json/custom-memberships/v1/keys/' + id,
      method:  'DELETE',
      headers: { 'X-WP-Nonce': cmAdmin.nonce },
      success: function () {
        $('#cm-key-row-' + id).fadeOut(300, function () { $(this).remove(); });
      },
      error: function () { alert(cmAdmin.i18n.error); },
    });
  });

})(jQuery);
