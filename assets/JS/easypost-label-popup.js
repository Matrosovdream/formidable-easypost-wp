(function($){
    if (!window.epPopup) { window.epPopup = {}; } // safety
  
    const st = { ajaxUrl: epPopup.ajaxUrl, nonce: epPopup.nonce };
  
    // ---- Address helpers ----
    const FIELDS = ['name','phone','street1','street2','city','state','zip'];
  
    function populateAddressSelect($select, addresses) {
      $select.empty().append('<option value="">Choose saved address…</option>');
      if (!Array.isArray(addresses)) return;
      addresses.forEach(function(a, i){
        const labelParts = [];
        if (a.name)    labelParts.push(a.name);
        if (a.street1) labelParts.push(a.street1);
        let cityState = '';
        if (a.city)  cityState += a.city;
        if (a.state) cityState += (cityState ? ', ' : '') + a.state;
        if (cityState) labelParts.push(cityState);
        if (a.zip)   labelParts.push(a.zip);
        const label = labelParts.join(' — ');
        const $opt = $('<option>', { value: String(i), text: label });
        $opt.data('address', a);
        $select.append($opt);
      });
    }
  
    function applyAddressTo(prefix, addr) {
      if (!addr) return;
      FIELDS.forEach(function(key){
        const el = document.querySelector('#ep-' + prefix + '-' + key);
        if (el) el.value = addr[key] ?? '';
      });
    }
  
    // ---- Status helpers ----
    function setStatus(msg, ok){
      $('#ep-ep-status').text(msg).removeClass('ok err').addClass(ok ? 'ok' : 'err');
    }
    function setVerifyStatus(selector, ok, msg){
      const $el = $(selector);
      if(ok){
        $el.text('✓ Verified' + (msg ? ' — ' + msg : '')).removeClass('err').addClass('ok');
      } else {
        $el.text(msg ? ('✗ ' + msg) : '').removeClass('ok').addClass('err');
      }
    }
    function setNormalized(selector, normalized){
      const $el = $(selector);
      if (!normalized || typeof normalized !== 'object') {
        $el.text('').hide();
        return;
      }
      const line1 = normalized.delivery_line_1 || '';
      const last  = normalized.last_line || '';
      const out   = [line1, last].filter(Boolean).join(', ');
      if (out) {
        $el.text('Verified address: ' + out).show();
      } else {
        $el.text('').hide();
      }
    }
  
    // ---- Buy button toggle ----
    function toggleBuyButton(){
      const hasShipment = ($('#shipment_id').val() || '').trim().length > 0;
      const hasRate = ($('#ep-ep-rates').val() || '').trim().length > 0;
      $('#ep-ep-buy').prop('disabled', !(hasShipment && hasRate));
    }
  
    // ---- Verify a prefix (from/to) via server (Smarty-backed) ----
    function verifyPrefix(prefix){
      const statusSel = (prefix === 'from') ? '#ep-verify-from-status' : '#ep-verify-to-status';
      const normSel   = (prefix === 'from') ? '#ep-from-normalized'   : '#ep-to-normalized';
  
      const data = {
        name:    $('#ep-' + prefix + '-name').val(),
        street1: $('#ep-' + prefix + '-street1').val(),
        street2: $('#ep-' + prefix + '-street2').val(),
        city:    $('#ep-' + prefix + '-city').val(),
        state:   $('#ep-' + prefix + '-state').val(),
        zip:     $('#ep-' + prefix + '-zip').val(),
        phone:   $('#ep-' + prefix + '-phone').val()
      };
  
      setVerifyStatus(statusSel, true, 'Verifying…');
      setNormalized(normSel, null);
  
      $.post(st.ajaxUrl, {
        action: 'easypost_verify_address',
        _ajax_nonce: st.nonce,
        address: JSON.stringify(data),
        strict: 1
      }).done(function(resp){
        if (resp && resp.success && resp.data && resp.data.status === 'verified') {
          setVerifyStatus(statusSel, true, '');
          setNormalized(normSel, resp.data.normalized || null);
        } else {
          const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Verification failed.';
          setVerifyStatus(statusSel, false, msg);
          setNormalized(normSel, null);
        }
      }).fail(function(){
        setVerifyStatus(statusSel, false, 'Server error.');
        setNormalized(normSel, null);
      });
    }
  
    // ---- Form reader ----
    function readForm(){
      return {
        entry_id:  $('#ep-entry-id').val(),
        from_address: {
          name:    $('#ep-from-name').val(),
          street1: $('#ep-from-street1').val(),
          street2: $('#ep-from-street2').val(),
          city:    $('#ep-from-city').val(),
          state:   $('#ep-from-state').val(),
          zip:     $('#ep-from-zip').val(),
          phone:   $('#ep-from-phone').val()
        },
        to_address: {
          name:    $('#ep-to-name').val(),
          street1: $('#ep-to-street1').val(),
          street2: $('#ep-to-street2').val(),
          city:    $('#ep-to-city').val(),
          state:   $('#ep-to-state').val(),
          zip:     $('#ep-to-zip').val(),
          phone:   $('#ep-to-phone').val()
        },
        parcel: {
          length: parseFloat($('#ep-parcel-length').val() || '0'),
          width:  parseFloat($('#ep-parcel-width').val()  || '0'),
          height: parseFloat($('#ep-parcel-height').val() || '0'),
          weight: parseFloat($('#ep-parcel-weight').val() || '0'),
        },
        label_message1: $('#ep-label-msg1').val(),
        label_message2: $('#ep-label-msg2').val()
      };
    }
  
    // ---- Modal open/close ----
    $(document).on('click', '.ep-open-easypost', function(e){
      $('#ep-ep-label-link').empty();
      $('#ep-ep-tracking-link').empty();
  
      e.preventDefault();
      const entryId = $(this).data('entryId') || '';
      $('#ep-entry-id').val(entryId);
      $('#shipment_id').val('');
      $('#ep-ep-rates').empty().append('<option value="">No rates yet</option>');
      $('#ep-ep-title').text('EasyPost — Create Label for #' + entryId);
      $('#ep-ep-status').text('').removeClass('ok err');
  
      // Prefill label messages from localized data (optional)
      if (epPopup.prefill) {
        if (typeof epPopup.prefill.label_message1 === 'string') {
          $('#ep-label-msg1').val(epPopup.prefill.label_message1);
        }
        if (typeof epPopup.prefill.label_message2 === 'string') {
          $('#ep-label-msg2').val(epPopup.prefill.label_message2);
        }
      }
  
      // Clear verify + normalized outputs
      $('#ep-verify-from-status, #ep-verify-to-status').text('').removeClass('ok err');
      setNormalized('#ep-from-normalized', null);
      setNormalized('#ep-to-normalized', null);
  
      $('#ep-ep-modal').addClass('show');
      toggleBuyButton();
  
      // fetch saved addresses
      $.post(st.ajaxUrl, {
        action: 'easypost_get_entry_addresses',
        _ajax_nonce: st.nonce,
        entry_id: entryId
      })
      .done(function(resp){
        const addresses = (resp && resp.success && Array.isArray(resp.data.addresses)) ? resp.data.addresses : [];
        populateAddressSelect($('#ep-from-select'), addresses);
        populateAddressSelect($('#ep-to-select'),   addresses);
  
        // Auto-select the "Selected" address for the TO section
        try {
          const selectedIdx = addresses.findIndex(function(a){
            const v = a && a.Selected;
            return v === true || v === 'true' || v === 1 || v === '1';
          });
  
          if (selectedIdx >= 0) {
            $('#ep-to-select').val(String(selectedIdx));
            const $opt = $('#ep-to-select').find('option:selected');
            const addr = $opt.data('address');
            if (addr) {
              applyAddressTo('to', addr);
            }
          }
        } catch(e) {}
      })
      .fail(function(){
        populateAddressSelect($('#ep-from-select'), []);
        populateAddressSelect($('#ep-to-select'),   []);
      });
    });
  
    $(document).on('click', '#ep-ep-close, #ep-ep-modal', function(e){
      if(e.target.id==='ep-ep-modal' || e.target.id==='ep-ep-close'){
        $('#ep-ep-modal').removeClass('show');
      }
    });
  
    // ---- Select change -> autofill + auto-verify
    $(document).on('change', '.ep-address-select', function(){
      const $sel = $(this);
      const idx = parseInt($sel.val() || '-1', 10);
      if (idx < 0) return;
  
      let prefix = $sel.data('targetPrefix');
      if (!prefix) {
        const $wrap = $sel.closest('.ep-legend-wrap');
        prefix = $wrap.length ? $wrap.attr('data-prefix') : '';
      }
      if (!prefix) return;
  
      const addr = $sel.find('option:selected').data('address');
      if (addr) {
        applyAddressTo(prefix, addr);
        verifyPrefix(prefix);
      }
    });
  
    // ---- Calculate rates ----
    $(document).on('click', '#ep-ep-calc', function(e){
      e.preventDefault();
      setStatus('Calculating rates…', true);
  
      const payload = readForm();
      $.post(st.ajaxUrl, {
        action: 'easypost_calculate_rates',
        _ajax_nonce: st.nonce,
        data: JSON.stringify(payload)
      }).done(function(resp){
        const $sel = $('#ep-ep-rates');
        $sel.empty();
  
        if(resp && resp.success){
          $('#shipment_id').val(resp.data.general.id || '');
          const rates = Array.isArray(resp.data.rates) ? resp.data.rates : [];
          if (rates.length === 0) {
            $sel.append('<option value="">No rates found</option>');
            setStatus('No rates returned for this shipment.', false);
          } else {
            $sel.append('<option value="">Choose a rate…</option>');
            rates.forEach(function(r){
              const text = r.carrier + ' — ' + r.service + ' — ' + r.rate + ' ' + r.currency +
                          (r.est_delivery_days ? (' ('+r.est_delivery_days+' days)') : '');
              $('#ep-ep-rates').append(
                $('<option>', { value: r.id, text: text }).data('rate', r)
              );
            });
            setStatus('Rates loaded. Choose a rate to continue.', true);
          }
        } else {
          const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to calculate rates.';
          $sel.append('<option value="">No rates</option>');
          setStatus(msg, false);
        }
      }).fail(function(){
        $('#ep-ep-rates').empty().append('<option value="">Error loading rates</option>');
        setStatus('Server error while getting rates.', false);
      }).always(function(){
        $('#ep-ep-calc').prop('disabled', false);
        toggleBuyButton();
      });
    });
  
    // ---- Buy label ----
    $(document).on('click', '#ep-ep-buy', function(e){
      e.preventDefault();
      const shipmentId = $('#shipment_id').val();
      const rateId = $('#ep-ep-rates').val();
      if(!shipmentId || !rateId){ setStatus('Select a rate first.', false); return; }
  
      setStatus('Buying label…', true);
      $('#ep-ep-buy, #ep-ep-calc').prop('disabled', true);
  
      const msg1 = $('#ep-label-msg1').val();
      const msg2 = $('#ep-label-msg2').val();
  
      $.post(st.ajaxUrl, {
        action: 'easypost_create_label',
        _ajax_nonce: st.nonce,
        shipment_id: shipmentId,
        rate_id: rateId,
        label_message1: msg1,
        label_message2: msg2
      }).done(function(resp){
        if(resp && resp.success){
          setStatus('Label purchased. Tracking: ' + (resp.data.general.tracking_code || 'N/A'), true);
  
          if( resp.data.label && resp.data.label.label_url ){
            $('#ep-ep-tracking-link').html(
              '<a href="" ' +
              'onclick="window.open(\'' + resp.data.label.label_url.replace(/'/g, "\\'") + '\', \'Print label\', \'width=610,height=700\'); return false;">' +
              'Print Label' +
              '</a>'
            );
          }
  
          if (resp.data.general && resp.data.general.tracking_url) {
            $('#ep-ep-label-link').html(
              '<a href="' + resp.data.general.tracking_url.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener">Tracking Page</a>'
            );
          }
  
        } else {
          const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to buy label.';
          setStatus(msg, false);
        }
      }).fail(function(){
        setStatus('Server error while buying label.', false);
      }).always(function(){
        $('#ep-ep-buy, #ep-ep-calc').prop('disabled', false);
      });
    });
  
    // ---- Verify buttons ----
    $(document).on('click', '#ep-verify-from', function(e){
      e.preventDefault();
      verifyPrefix('from');
    });
    $(document).on('click', '#ep-verify-to', function(e){
      e.preventDefault();
      verifyPrefix('to');
    });
  
    // ---- Rate changed -> set shipment + enable buy ----
    $(document).on('change', '#ep-ep-rates', function(){
      const $opt = $(this).find('option:selected');
      if ($opt.length) {
        const rate = $opt.data('rate');
        if (rate && rate.shipment_id) {
          $('#shipment_id').val(rate.shipment_id);
        }
      }
      toggleBuyButton();
    });
  
  })(jQuery);
  