(function(){
    // Delegated click: open the void modal from any shipments list on the page
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.js-easyspot-void-open');
      if (!btn) return;
      e.preventDefault();
      if (btn.disabled) return;
      const easypostId = btn.getAttribute('data-easypost-id') || '';
      const entryId    = btn.getAttribute('data-entry-id') || '';
      if (typeof window.easyspotOpenVoidModal !== 'function') {
        alert('Void modal not available. Place [easypost-void-modal] on this page.');
        return;
      }
      window.easyspotOpenVoidModal(easypostId, entryId);
    });
  })();
  