/* Custom Memberships — Frontend JS */
(function ($) {
  'use strict';

  var wrap      = $('#cm-membership-form');
  var notices   = wrap.find('.cm-notices');
  var panel1    = $('#cm-panel-1');
  var panel2    = $('#cm-panel-2');
  var step1Dot  = wrap.find('[data-step="1"]');
  var step2Dot  = wrap.find('[data-step="2"]');
  var step1Data = null; // validated data from step 1

  // ── Helpers ──────────────────────────────────────────────────────

  function showNotice(msg, type) {
    type = type || 'error';
    notices.html('<div class="cm-notice cm-notice--' + type + '">' + msg + '</div>');
    notices[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearNotices() {
    notices.empty();
  }

  function setLoading($btn, loading) {
    if (loading) {
      $btn.addClass('cm-btn--loading').attr('disabled', true);
    } else {
      $btn.removeClass('cm-btn--loading').attr('disabled', false);
    }
  }

  function fieldError($input, msg) {
    $input.addClass('cm-field--error');
    var $hint = $input.siblings('.cm-field__error');
    if (!$hint.length) {
      $input.after('<span class="cm-field__error">' + msg + '</span>');
    } else {
      $hint.text(msg);
    }
  }

  function clearFieldErrors($form) {
    $form.find('.cm-field--error').removeClass('cm-field--error');
    $form.find('.cm-field__error').remove();
  }

  function goToStep2() {
    panel1.removeClass('cm-panel--active');
    panel2.addClass('cm-panel--active');
    step1Dot.removeClass('cm-step--active');
    step2Dot.addClass('cm-step--active');
    wrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function goToStep1() {
    panel2.removeClass('cm-panel--active');
    panel1.addClass('cm-panel--active');
    step2Dot.removeClass('cm-step--active');
    step1Dot.addClass('cm-step--active');
    clearNotices();
    wrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ── Step 1 submit ─────────────────────────────────────────────────

  $('#cm-form-step1').on('submit', function (e) {
    e.preventDefault();
    clearNotices();
    clearFieldErrors($(this));

    var $btn   = $(this).find('button[type="submit"]');
    var nonce  = $(this).find('#cm_nonce').val();

    var name     = $.trim($('#cm_name').val());
    var email    = $.trim($('#cm_email').val());
    var phone    = $.trim($('#cm_phone').val());
    var location = $.trim($('#cm_location').val());

    // Client-side pre-validation.
    var ok = true;
    if (!name)    { fieldError($('#cm_name'),     cmData.i18n.required); ok = false; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      fieldError($('#cm_email'), cmData.i18n.invalid_email); ok = false;
    }
    if (!phone)   { fieldError($('#cm_phone'),    cmData.i18n.required); ok = false; }
    if (!location){ fieldError($('#cm_location'), cmData.i18n.required); ok = false; }
    if (!ok) return;

    setLoading($btn, true);

    $.ajax({
      url:    cmData.ajaxUrl,
      method: 'POST',
      data: {
        action:      'cm_submit_step1',
        cm_nonce:    nonce,
        cm_name:     name,
        cm_email:    email,
        cm_phone:    phone,
        cm_location: location,
      },
      success: function (res) {
        if (res.success) {
          step1Data = res.data.data;
          // Pass to step 2 form hidden field.
          $('#cm_step1_data').val(JSON.stringify(step1Data));
          goToStep2();
        } else {
          if (res.data && res.data.errors) {
            var fieldMap = {
              cm_name:     '#cm_name',
              cm_email:    '#cm_email',
              cm_phone:    '#cm_phone',
              cm_location: '#cm_location',
            };
            $.each(res.data.errors, function (key, msg) {
              if (fieldMap[key]) fieldError($(fieldMap[key]), msg);
            });
          } else {
            showNotice(res.data && res.data.message ? res.data.message : cmData.i18n.error);
          }
        }
      },
      error: function () {
        showNotice(cmData.i18n.error);
      },
      complete: function () {
        setLoading($btn, false);
      },
    });
  });

  // ── Back button ───────────────────────────────────────────────────

  $('#cm-back-btn').on('click', function () {
    goToStep1();
  });

  // ── Step 2 submit ─────────────────────────────────────────────────

  $('#cm-form-step2').on('submit', function (e) {
    e.preventDefault();
    clearNotices();

    var $btn       = $(this).find('button[type="submit"]');
    var nonce      = $(this).find('#cm_nonce_step2').val();
    var packageId  = $('input[name="cm_package_id"]:checked').val();
    var step1Json  = $('#cm_step1_data').val();

    if (!packageId) {
      showNotice(cmData.i18n.select_plan);
      return;
    }

    if (!step1Json) {
      showNotice('Your details are missing. Please go back and try again.');
      goToStep1();
      return;
    }

    setLoading($btn, true);

    $.ajax({
      url:    cmData.ajaxUrl,
      method: 'POST',
      data: {
        action:         'cm_submit_step2',
        cm_nonce_step2: nonce,
        cm_package_id:  packageId,
        cm_step1_data:  step1Json,
      },
      success: function (res) {
        if (res.success && res.data.redirect) {
          showNotice(cmData.i18n.processing, 'success');
          window.location.href = res.data.redirect;
        } else {
          showNotice(res.data && res.data.message ? res.data.message : cmData.i18n.error);
          setLoading($btn, false);
        }
      },
      error: function () {
        showNotice(cmData.i18n.error);
        setLoading($btn, false);
      },
    });
  });

})(jQuery);
