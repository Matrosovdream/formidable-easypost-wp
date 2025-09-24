(function($){
    const st = { ajaxUrl: (window.epEntryVerify && epEntryVerify.ajaxUrl) || '', nonce: (window.epEntryVerify && epEntryVerify.nonce) || '' };
  
    function setVerifyStatus($group, ok, msg){
      const $el = $group.find('.epv-verify-status');
      if(ok){
        $el.text('✓ Verified' + (msg ? ' — ' + msg : '')).removeClass('err').addClass('ok');
      } else {
        $el.text(msg ? ('✗ ' + msg) : '').removeClass('ok').addClass('err');
      }
    }
  
    function setNormalized($group, normalized){
      const $el = $group.find('.epv-normalized');
      if (!normalized || typeof normalized !== 'object') {
        $el.text('').hide();
        return;
      }
      const line1 = normalized.delivery_line_1 || '';
      const last  = normalized.last_line || '';
      const out   = [line1, last].filter(Boolean).join(', ');
      if (out) { $el.text('Normalized address: ' + out).show(); }
      else { $el.text('').hide(); }
    }
  
    function readFields($group){
      return {
        name:    $group.find('.epv-name').val(),
        street1: $group.find('.epv-street1').val(),
        street2: $group.find('.epv-street2').val(),
        city:    $group.find('.epv-city').val(),
        state:   $group.find('.epv-state').val(),
        zipcode: $group.find('.epv-zip').val(),
        country: 'US'
      };
    }
  
    function verifyNow($group){
      setVerifyStatus($group, true, 'Verifying…');
      setNormalized($group, null);
  
      $.post(st.ajaxUrl, {
        action: 'entry_verify_address',
        _ajax_nonce: st.nonce,
        address: JSON.stringify(readFields($group)),
        strict: 1
      }).done(function(resp){
        if (resp && resp.success && resp.data && resp.data.status === 'verified') {
          setVerifyStatus($group, true, '');
          setNormalized($group, resp.data.normalized || null);
        } else {
          const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Verification failed.';
          setVerifyStatus($group, false, msg);
          setNormalized($group, null);
        }
      }).fail(function(){
        setVerifyStatus($group, false, 'Server error.');
        setNormalized($group, null);
      });
    }
  
    // Delegated handlers — supports multiple shortcodes on one page
    $(document).on('click', '.epv-group .epv-verify', function(e){
      e.preventDefault();
      const $group = $(this).closest('.epv-group');
      verifyNow($group);
    });
  
    $(document).on('click', '.epv-group .epv-toggle-fields', function(e){
      e.preventDefault();
      const $group = $(this).closest('.epv-group');
      const $form = $group.find('.epv-form');
      $form.slideToggle(150);
      const showing = $form.is(':visible');
      $(this).text(showing ? 'Hide fields' : 'Show fields');
    });
  
  })(jQuery);
  