(function(){
    const $ = (sel, root=document) => root.querySelector(sel);
    const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

    // Toggle details
    $$('.fs-details-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-target');
            const row = document.getElementById(id);
            if (!row) return;
            const isOpen = row.style.display !== 'none';
            row.style.display = isOpen ? 'none' : '';
            btn.setAttribute('aria-expanded', String(!isOpen));
            btn.textContent = isOpen ? 'Show details' : 'Hide details';
        });
    });

    // Void action
    $$('.fs-void-btn').forEach(btn => {
        btn.addEventListener('click', async () => {

            const epId   = btn.getAttribute('data-ep-id');
            const entryVal = btn.getAttribute('data-row-id') || '';
            window.easyspotOpenVoidModal(epId, entryVal);

        });
    });
    
})();


// Void action
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