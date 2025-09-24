(function(){
    const overlay   = document.getElementById('easyspot-void-modal-overlay');
    if (!overlay) return;
    const modal     = document.getElementById('easyspot-void-modal');
    const btnClose  = document.getElementById('easyspot-void-close');
    const btnCancel = document.getElementById('easyspot-void-cancel');
    const btnConfirm= document.getElementById('easyspot-void-confirm');
    const resBox    = document.getElementById('easyspot-void-result');
    const entrySpan = document.getElementById('easyspot-void-entry');
    const fieldId   = document.getElementById('easyspot-void-easypost-id');
    const fieldEntry= document.getElementById('easyspot-void-entry-id');
    const AJAX = overlay.dataset.ajax || '';
    const NONCE= overlay.dataset.nonce || '';
  
    // Ensure overlay is in body root
    if (overlay.parentElement !== document.body) {
      document.body.appendChild(overlay);
    }
  
    function openModal(easypostId, entryId) {
      fieldId.value    = easypostId || '';
      fieldEntry.value = entryId || '';
      entrySpan.textContent = entryId || '—';
      resBox.style.display = 'none';
      resBox.className = '';
      resBox.textContent = '';
      btnConfirm.disabled = false;
  
      overlay.classList.add('show');
      overlay.style.display = 'flex';
      document.body.classList.add('easyspot-modal-open');
      btnClose && btnClose.focus && btnClose.focus();
    }
    function closeModal() {
      overlay.classList.remove('show');
      overlay.style.display='none';
      document.body.classList.remove('easyspot-modal-open');
    }
  
    window.easyspotOpenVoidModal = openModal;
  
    if (btnClose)  btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e){
      if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', (ev)=>{
      if (overlay.classList.contains('show') && ev.key === 'Escape') closeModal();
    });
  
    if (btnConfirm) btnConfirm.addEventListener('click', async function(){
      const easypostId = fieldId.value.trim();
      if (!easypostId) {
        resBox.textContent = 'Missing shipment ID.';
        resBox.className = 'error';
        resBox.style.display = 'block';
        return;
      }
      btnConfirm.disabled = true;
      const orig = btnConfirm.textContent;
      btnConfirm.textContent = 'Voiding…';
  
      try {
        const body = new URLSearchParams();
        body.set('action','easyspot_void_shipment');
        body.set('easypost_id', easypostId);
        body.set('nonce', NONCE);
  
        const resp = await fetch(AJAX, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
        const json = await resp.json();
  
        if (json && json.success) {
          resBox.textContent = 'Success: the shipment has been voided.';
          resBox.className = 'success';
          resBox.style.display = 'block';
  
          const row = document.querySelector('.easyspot-shipment[data-easypost-id="'+CSS.escape(easypostId)+'"]');
          if (row) {
            row.style.opacity = '0.65';
            const btn = row.querySelector('.js-easyspot-void-open');
            if (btn) { btn.textContent = 'Voided'; btn.disabled = true; }
            const statusEl = row.querySelector('.easyspot-info div:nth-child(2)');
            if (statusEl) { statusEl.innerHTML = '<strong>Status:</strong> Voided'; }
          }
        } else {
          const msg = (json && json.data && json.data.message) ? json.data.message : 'Error voiding shipment.';
          resBox.textContent = msg;
          resBox.className = 'error';
          resBox.style.display = 'block';
          btnConfirm.disabled = false;
        }
      } catch (e) {
        resBox.textContent = 'Network error.';
        resBox.className = 'error';
        resBox.style.display = 'block';
        btnConfirm.disabled = false;
      } finally {
        btnConfirm.textContent = orig;
      }
    });
  })();
  