(function () {
    // Delegate clicks for ALL shortcode instances on the page
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.shp-void-btn');
      if (!btn) return;
  
      var wrap = btn.closest('.shp-orders-wrap');
      if (!wrap) return;
  
      var shipmentId = btn.getAttribute('data-shipment');
      if (!shipmentId) return;
  
      var ajaxUrl = wrap.getAttribute('data-ajax');
      var nonce   = wrap.getAttribute('data-nonce');
  
      btn.disabled = true;
      var originalText = btn.textContent;
      btn.textContent = 'Voiding...';
  
      var form = new FormData();
      form.append('action', 'shp_void_shipment');
      form.append('shipment_id', shipmentId);
      form.append('nonce', nonce);
  
      fetch(ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            var li = btn.closest('.shp-shipment');
            if (!li) return;
  
            // Mark voided
            var stateEl = li.querySelector('.shp-void-state');
            if (stateEl) stateEl.textContent = 'yes';
  
            // Show voided_at
            var voidedStr = (res.data && res.data.voided_at) ? res.data.voided_at : '';
            if (voidedStr) {
              var info = li.querySelector('.shp-voided-at');
              if (!info) {
                info = document.createElement('span');
                info.className = 'shp-voided-at';
                var lastLine = li.querySelector('.shp-shipment-line:last-child');
                if (lastLine) lastLine.appendChild(info);
              }
              info.textContent = ' (voided_at: ' + voidedStr + ')';
            }
  
            btn.remove();
          } else {
            btn.disabled = false;
            btn.textContent = originalText;
            var msg = (res && res.data && res.data.message) ? res.data.message : 'Unable to void shipment.';
            alert(msg);
          }
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = originalText;
          alert('Network error while voiding shipment.');
        });
    }, false);
  })();


  (function () {
    // Toggle Add Label form
    document.addEventListener('click', function (e) {
      // Open toggle
      var toggle = e.target.closest('.shp-label-toggle');
      if (toggle) {
        var targetSel = toggle.getAttribute('data-target');
        if (targetSel) {
          var form = document.querySelector(targetSel);
          if (form) {
            var isOpen = form.classList.contains('is-open');
            if (!isOpen) {
              form.classList.add('is-open');
              toggle.setAttribute('aria-expanded', 'true');
            } else {
              form.classList.remove('is-open');
              toggle.setAttribute('aria-expanded', 'false');
            }
          }
        }
        e.preventDefault();
        return;
      }
  
      // Cancel button
      var cancelBtn = e.target.closest('.shp-label-cancel');
      if (cancelBtn) {
        var cancelTarget = cancelBtn.getAttribute('data-target');
        if (cancelTarget) {
          var form = document.querySelector(cancelTarget);
          if (form) {
            form.classList.remove('is-open');
            // Reset aria-expanded on toggle if exists
            var toggleBtn = form.parentElement.querySelector('.shp-label-toggle');
            if (toggleBtn) {
              toggleBtn.setAttribute('aria-expanded', 'false');
            }
          }
        }
        e.preventDefault();
        return;
      }
    });
  })();
  
  