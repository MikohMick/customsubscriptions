/* Custom Memberships — Admin JS */
(function ($) {
  'use strict';

  var nonce = cmAdmin.nonce;

  // ── Utilities ─────────────────────────────────────────────────────

  function escHtml(str) {
    return $('<div>').text(str || '').html();
  }

  function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
  }

  function formatPrice(price) {
    return cmAdmin.currencySymbol + parseFloat(price || 0).toLocaleString('en', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function flashRow($row, color) {
    color = color || '#e6f4ea';
    $row.css('background', color);
    setTimeout(function () { $row.css('background', ''); }, 1000);
  }

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
    $modal.find('.cm-modal-notice')
      .removeClass('is-error is-success')
      .addClass('is-' + type)
      .text(msg)
      .show();
  }

  function clearModalNotice($modal) {
    $modal.find('.cm-modal-notice').hide().text('');
  }

  $(document).on('click', '.cm-modal-close', function () {
    closeModal($(this).closest('.cm-modal'));
  });

  // ── Packages page ─────────────────────────────────────────────────

  var $pkgModal = $('#cm-package-modal');

  function renderPackageRow(pkg) {
    var sessionsHtml = parseInt(pkg.sessions) === 0
      ? '<span class="cm-badge cm-badge--unlimited">' + cmAdmin.i18n.unlimited + '</span>'
      : escHtml(pkg.sessions);

    var activeHtml = parseInt(pkg.active)
      ? '<span class="cm-badge cm-badge--active">Yes</span>'
      : '<span class="cm-badge cm-badge--inactive">No</span>';

    return {
      name:        '<strong>' + escHtml(pkg.name) + '</strong>',
      description: escHtml((pkg.description || '').substring(0, 60)),
      sessions:    sessionsHtml,
      price:       escHtml(formatPrice(pkg.price)),
      sort_order:  escHtml(pkg.sort_order),
      active:      activeHtml,
    };
  }

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
    var isNew = $('#cm-pkg-id').val() === '0';
    var $btn  = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');

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
      if (!res.success) {
        showModalNotice($pkgModal, res.data.message || cmAdmin.i18n.error, 'error');
        return;
      }

      showModalNotice($pkgModal, cmAdmin.i18n.saved, 'success');

      if (isNew) {
        // New package — reload to get the full row with product link.
        setTimeout(function () { location.reload(); }, 700);
        return;
      }

      // Edit — update row in place.
      var pkg  = res.data.package;
      var $row = $('#cm-package-row-' + pkg.id);
      var cells = renderPackageRow(pkg);

      $row.find('td').eq(0).html(cells.name);
      $row.find('td').eq(1).html(cells.description);
      $row.find('td').eq(2).html(cells.sessions);
      $row.find('td').eq(3).html(cells.price);
      $row.find('td').eq(4).html(cells.sort_order);
      $row.find('td').eq(5).html(cells.active);

      // Refresh the data attribute so next edit opens fresh values.
      $row.find('.cm-btn-edit-package').attr('data-package', JSON.stringify(pkg));

      flashRow($row);
      setTimeout(function () { closeModal($pkgModal); }, 700);

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

  function renderMemberSessionsCell(m) {
    if (parseInt(m.sessions_total) === 0) {
      return '<span class="cm-badge cm-badge--unlimited">' + cmAdmin.i18n.unlimited + '</span>';
    }
    var zero = parseInt(m.sessions_remaining) === 0;
    return '<span class="cm-sessions-remaining' + (zero ? ' cm-sessions-zero' : '') + '">'
      + escHtml(m.sessions_remaining) + ' / ' + escHtml(m.sessions_total)
      + '</span>'
      + ' <button class="cm-btn-use-session button button-small" data-id="' + escHtml(m.id) + '" title="Use 1 session">&minus;1</button>';
  }

  function updateMemberRow(m) {
    var $row = $('#cm-member-row-' + m.id);
    if (!$row.length) return false;

    $row.find('td').eq(0).html('<strong>' + escHtml(m.name) + '</strong>');
    $row.find('td').eq(1).html('<a href="mailto:' + escHtml(m.email) + '">' + escHtml(m.email) + '</a>');
    $row.find('td').eq(2).text(m.phone);
    $row.find('td').eq(3).text(m.location);
    $row.find('td').eq(4).text(m.package_name || '');
    $row.find('td').eq(5).html(renderMemberSessionsCell(m));
    $row.find('td').eq(6).html(
      '<span class="cm-status cm-status--' + escHtml(m.status) + '">' + capitalize(m.status) + '</span>'
    );

    // Keep data attribute fresh for next edit.
    $row.find('.cm-btn-edit-member').attr('data-member', JSON.stringify(m));

    return true;
  }

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
    $('#cm-member-modal-title').text('Edit Member — ' + m.name);
    $mbrModal.find('.cm-field--new-only').hide();
    $mbrModal.find('.cm-field--edit-only').show();
    clearModalNotice($mbrModal);
    openModal($mbrModal);
  });

  // Save member.
  $('#cm-member-form').on('submit', function (e) {
    e.preventDefault();
    var isNew = $('#cm-member-id').val() === '0';
    var $btn  = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');

    $.post(cmAdmin.ajaxUrl, {
      action:             'cm_admin_save_member',
      nonce:              nonce,
      id:                 $('#cm-member-id').val(),
      name:               $('#cm-m-name').val(),
      email:              $('#cm-m-email').val(),
      phone:              $('#cm-m-phone').val(),
      location:           $('#cm-m-location').val(),
      package_id:         $('#cm-m-package').val(),
      sessions_remaining: $('#cm-m-sessions').val(),
      status:             $('#cm-m-status').val(),
      notes:              $('#cm-m-notes').val(),
    }, function (res) {
      if (!res.success) {
        showModalNotice($mbrModal, res.data.message || cmAdmin.i18n.error, 'error');
        return;
      }

      if (isNew) {
        showModalNotice($mbrModal, cmAdmin.i18n.new_member, 'success');
        setTimeout(function () { location.reload(); }, 700);
        return;
      }

      // Edit — update row in place, close modal.
      showModalNotice($mbrModal, cmAdmin.i18n.saved, 'success');
      var m = res.data.member;
      if (updateMemberRow(m)) {
        flashRow($('#cm-member-row-' + m.id));
        setTimeout(function () { closeModal($mbrModal); }, 600);
      } else {
        setTimeout(function () { location.reload(); }, 700);
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
    var id   = $(this).data('id');
    var $btn = $(this).prop('disabled', true);
    $.post(cmAdmin.ajaxUrl, { action: 'cm_admin_use_session', nonce: nonce, id: id, amount: 1 }, function (res) {
      if (res.success) {
        var m   = res.data.member;
        var $td = $btn.closest('td');
        $td.html(renderMemberSessionsCell(m));
        $('#cm-member-row-' + m.id).find('.cm-btn-edit-member').attr('data-member', JSON.stringify(m));
        flashRow($btn.closest('tr'));
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

  $('#cm-btn-add-key').on('click', openKeyModal);
  $('#cm-key-modal-cancel, #cm-key-modal-close, #cm-key-modal-overlay').on('click', function () {
    closeModal($keyModal);
  });
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

  $('#cm-copy-key').on('click', function () {
    var $input = $('#cm-generated-key');
    $input[0].select();
    document.execCommand('copy');
    $(this).text(cmAdmin.i18n.copy_success);
  });

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
