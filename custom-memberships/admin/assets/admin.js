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

  // Tracks the category a package had when the modal opened, so we know
  // whether the row needs to move to a different table on save.
  var pkgCategoryOnOpen = null;

  function pluralizeUnit(unit) {
    unit = (unit || 'Session').trim();
    return /(s|x|z|ch|sh)$/i.test(unit) ? unit + 'es' : unit + 's';
  }

  function sessionsLabel(sessions, unit) {
    sessions = parseInt(sessions, 10) || 0;
    if (sessions === 0) return cmAdmin.i18n.unlimited;
    return sessions + ' ' + (sessions === 1 ? (unit || 'Session') : pluralizeUnit(unit));
  }

  // Open for add.
  $(document).on('click', '.cm-btn-add-package', function () {
    $('#cm-pkg-id').val('0');
    $('#cm-package-form')[0].reset();
    $('#cm-pkg-active').prop('checked', true);
    $('#cm-pkg-highlight').prop('checked', false);
    $('#cm-pkg-unit').val('Session');
    $('#cm-pkg-category').val('0');
    pkgCategoryOnOpen = null;
    $('#cm-package-modal-title').text('Add Package');
    clearModalNotice($pkgModal);
    openModal($pkgModal);
  });

  // Open for edit.
  $(document).on('click', '.cm-btn-edit-package', function () {
    var pkg = $(this).data('package');               // jQuery auto-parses JSON data attrs
    if (typeof pkg === 'string') pkg = JSON.parse(pkg); // safety for .attr() writes

    $('#cm-pkg-id').val(pkg.id);
    $('#cm-pkg-name').val(pkg.name);
    $('#cm-pkg-price').val(pkg.price);
    $('#cm-pkg-sessions').val(pkg.sessions);
    $('#cm-pkg-unit').val(pkg.session_unit || 'Session');
    $('#cm-pkg-order').val(pkg.sort_order);
    $('#cm-pkg-desc').val(pkg.description || '');
    $('#cm-pkg-perks').val(pkg.perks || '');
    $('#cm-pkg-category').val(String(parseInt(pkg.category_id, 10) || 0));
    $('#cm-pkg-active').prop('checked', parseInt(pkg.active) === 1);
    $('#cm-pkg-highlight').prop('checked', parseInt(pkg.highlight) === 1);

    pkgCategoryOnOpen = parseInt(pkg.category_id, 10) || 0;

    $('#cm-package-modal-title').text('Edit Package — ' + pkg.name);
    clearModalNotice($pkgModal);
    openModal($pkgModal);
  });

  // Save package.
  $('#cm-package-form').on('submit', function (e) {
    e.preventDefault();
    var isNew = $('#cm-pkg-id').val() === '0';
    var $btn  = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');

    $.post(cmAdmin.ajaxUrl, {
      action:       'cm_admin_save_package',
      nonce:        nonce,
      id:           $('#cm-pkg-id').val(),
      name:         $('#cm-pkg-name').val(),
      category_id:  $('#cm-pkg-category').val(),
      price:        $('#cm-pkg-price').val(),
      sessions:     $('#cm-pkg-sessions').val(),
      session_unit: $('#cm-pkg-unit').val(),
      sort_order:   $('#cm-pkg-order').val(),
      description:  $('#cm-pkg-desc').val(),
      perks:        $('#cm-pkg-perks').val(),
      active:       $('#cm-pkg-active').is(':checked') ? 1 : 0,
      highlight:    $('#cm-pkg-highlight').is(':checked') ? 1 : 0,
    }, function (res) {
      if (!res.success) {
        showModalNotice($pkgModal, res.data.message || cmAdmin.i18n.error, 'error');
        return;
      }

      showModalNotice($pkgModal, cmAdmin.i18n.saved, 'success');

      var pkg         = res.data.package;
      var newCategory = parseInt(pkg.category_id, 10) || 0;

      // New package, or one that moved category, changes the table grouping —
      // a reload is the only way to land it under the right heading.
      if (isNew || newCategory !== pkgCategoryOnOpen) {
        setTimeout(function () { location.reload(); }, 700);
        return;
      }

      // Same category — update the row in place.
      var $row  = $('#cm-package-row-' + pkg.id);
      var perks = (pkg.perks || '').split('\n').filter(function (p) { return p.trim(); });

      var nameHtml = '<strong>' + escHtml(pkg.name) + '</strong>';
      if (parseInt(pkg.highlight)) {
        nameHtml += ' <span class="cm-badge cm-badge--popular">Popular</span>';
      }

      var sessionsHtml = parseInt(pkg.sessions) === 0
        ? '<span class="cm-badge cm-badge--unlimited">' + cmAdmin.i18n.unlimited + '</span>'
        : escHtml(sessionsLabel(pkg.sessions, pkg.session_unit));

      $row.find('td').eq(0).html(nameHtml);
      $row.find('td').eq(1).html(sessionsHtml);
      $row.find('td').eq(2).text(formatPrice(pkg.price));
      $row.find('td').eq(3).text(perks.length ? perks.length + ' listed' : '—');
      $row.find('td').eq(4).text(pkg.sort_order);
      $row.find('td').eq(5).html(parseInt(pkg.active)
        ? '<span class="cm-badge cm-badge--active">Yes</span>'
        : '<span class="cm-badge cm-badge--inactive">No</span>');

      $row.find('.cm-btn-edit-package')
        .attr('data-package', JSON.stringify(pkg))
        .data('package', pkg);

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

  // ── Categories page ───────────────────────────────────────────────

  var $catModal = $('#cm-category-modal');

  $(document).on('click', '.cm-btn-add-category', function () {
    $('#cm-cat-id').val('0');
    $('#cm-category-form')[0].reset();
    $('#cm-cat-active').prop('checked', true);
    $('#cm-category-modal-title').text('Add Category');
    clearModalNotice($catModal);
    openModal($catModal);
  });

  $(document).on('click', '.cm-btn-edit-category', function () {
    var cat = $(this).data('category');
    if (typeof cat === 'string') cat = JSON.parse(cat);

    $('#cm-cat-id').val(cat.id);
    $('#cm-cat-name').val(cat.name);
    $('#cm-cat-subtitle').val(cat.subtitle || '');
    $('#cm-cat-desc').val(cat.description || '');
    $('#cm-cat-order').val(cat.sort_order);
    $('#cm-cat-active').prop('checked', parseInt(cat.active) === 1);

    $('#cm-category-modal-title').text('Edit Category — ' + cat.name);
    clearModalNotice($catModal);
    openModal($catModal);
  });

  $('#cm-category-form').on('submit', function (e) {
    e.preventDefault();
    var isNew = $('#cm-cat-id').val() === '0';
    var $btn  = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');

    $.post(cmAdmin.ajaxUrl, {
      action:      'cm_admin_save_category',
      nonce:       nonce,
      id:          $('#cm-cat-id').val(),
      name:        $('#cm-cat-name').val(),
      subtitle:    $('#cm-cat-subtitle').val(),
      description: $('#cm-cat-desc').val(),
      sort_order:  $('#cm-cat-order').val(),
      active:      $('#cm-cat-active').is(':checked') ? 1 : 0,
    }, function (res) {
      if (!res.success) {
        showModalNotice($catModal, res.data.message || cmAdmin.i18n.error, 'error');
        return;
      }

      showModalNotice($catModal, cmAdmin.i18n.saved, 'success');

      if (isNew) {
        setTimeout(function () { location.reload(); }, 700);
        return;
      }

      var cat  = res.data.category;
      var $row = $('#cm-category-row-' + cat.id);

      $row.find('td').eq(0).html('<strong>' + escHtml(cat.name) + '</strong>');
      $row.find('td').eq(1).text(cat.subtitle || '');
      $row.find('td').eq(3).text(cat.sort_order);
      $row.find('td').eq(4).html(parseInt(cat.active)
        ? '<span class="cm-badge cm-badge--active">Yes</span>'
        : '<span class="cm-badge cm-badge--inactive">No</span>');

      $row.find('.cm-btn-edit-category')
        .attr('data-category', JSON.stringify(cat))
        .data('category', cat);

      flashRow($row);
      setTimeout(function () { closeModal($catModal); }, 700);

    }).fail(function () {
      showModalNotice($catModal, cmAdmin.i18n.error, 'error');
    }).always(function () {
      $btn.prop('disabled', false).text('Save Category');
    });
  });

  $(document).on('click', '.cm-btn-delete-category', function () {
    if (!confirm(cmAdmin.i18n.confirm_delete_category)) return;
    var id = $(this).data('id');
    $.post(cmAdmin.ajaxUrl, { action: 'cm_admin_delete_category', nonce: nonce, id: id }, function (res) {
      if (res.success) {
        $('#cm-category-row-' + id).fadeOut(300, function () { $(this).remove(); });
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

    // Keep data attribute AND jQuery cache fresh for next edit.
    $row.find('.cm-btn-edit-member').attr('data-member', JSON.stringify(m)).data('member', m);

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
    var m = $(this).data('member');
    if (typeof m === 'string') m = JSON.parse(m);
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
        $('#cm-member-row-' + m.id).find('.cm-btn-edit-member')
          .attr('data-member', JSON.stringify(m)).data('member', m);
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
