(function(){
    // Utility: delete-row buttons
    function bindDeletes(scope){
      (scope || document).querySelectorAll('.frm-eap-del-row').forEach(btn => {
        btn.onclick = function(){
          const table = this.closest('table');
          const tbody = table ? table.tBodies[0] : null;
          const tr = this.closest('tr');
          if (tbody && tr && tbody.rows.length > 1) tr.remove();
        };
      });
    }
  
    // Carrier accounts (Settings)
    const carriersTable = document.getElementById('frm-easypost-carriers');
    const addCarrierBtn = document.getElementById('frm-easypost-add-row');
    if (addCarrierBtn && carriersTable) {
      addCarrierBtn.onclick = function(){
        const tbody = carriersTable.tBodies[0];
        const idx = tbody.rows.length;
        const opt = window.FrmEAP ? FrmEAP.option : 'frm_easypost';
        const del = (FrmEAP && FrmEAP.i18n && FrmEAP.i18n.deleteRow) || 'Delete row';
        const tmpl = `
          <tr>
            <td><input type="text" name="${opt}[carrier_accounts][${idx}][code]" value="" placeholder="usps" class="regular-text" /></td>
            <td><input type="text" name="${opt}[carrier_accounts][${idx}][id]" value="" placeholder="ca_xxxxxxxxxxxxxxxxxxxxxxxx" class="regular-text" /></td>
            <td><input type="text" name="${opt}[carrier_accounts][${idx}][packages]" value="" placeholder="FlatRateEnvelope, FedExEnvelope" class="regular-text" /></td>
            <td><button type="button" class="button frm-eap-del-row" aria-label="${del}">✕</button></td>
          </tr>`;
        tbody.insertAdjacentHTML('beforeend', tmpl);
        bindDeletes(carriersTable);
      };
    }
  
    // Allowed carriers (Settings)
    const allowedTable  = document.getElementById('frm-easypost-allowed-carriers');
    const addAllowedBtn = document.getElementById('frm-easypost-add-allowed-row');
    if (addAllowedBtn && allowedTable) {
      addAllowedBtn.onclick = function(){
        const tbody = allowedTable.tBodies[0];
        const idx = tbody.rows.length;
        const opt = window.FrmEAP ? FrmEAP.option : 'frm_easypost';
        const del = (FrmEAP && FrmEAP.i18n && FrmEAP.i18n.deleteRow) || 'Delete row';
        const tmpl = `
          <tr>
            <td><input type="text" class="regular-text" name="${opt}[allowed_carriers][${idx}][carrier]" value="" placeholder="USPS, FedEx, FedExDefault" /></td>
            <td><input type="text" class="regular-text" name="${opt}[allowed_carriers][${idx}][services]" value="" placeholder="Express, Priority (leave empty for all)" /></td>
            <td><button type="button" class="button frm-eap-del-row" aria-label="${del}">✕</button></td>
          </tr>`;
        tbody.insertAdjacentHTML('beforeend', tmpl);
        bindDeletes(allowedTable);
      };
    }
  
    // Service addresses (Addresses page)
    const addressesTable = document.getElementById('frm-easypost-service-addresses');
    const addAddressBtn  = document.getElementById('frm-easypost-add-service-address');
    if (addAddressBtn && addressesTable) {
      addAddressBtn.onclick = function(){
        const tbody = addressesTable.tBodies[0];
        const idx = tbody.rows.length;
        const opt = window.FrmEAP ? FrmEAP.option : 'frm_easypost';
        const del = (FrmEAP && FrmEAP.i18n && FrmEAP.i18n.deleteRow) || 'Delete row';
        const tmpl = `
          <tr>
            <td>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][name]" value="" placeholder="Name" /><br/>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][company]" value="" placeholder="Company" /><br/>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][phone]" value="" placeholder="Phone" /><br/>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][proc_time]" value="" placeholder="Processing Time" />
            </td>
            <td>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][street1]" value="" placeholder="Street 1" /><br/>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][street2]" value="" placeholder="Street 2 (optional)" />
            </td>
            <td>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][city]" value="" placeholder="City" /><br/>
              <input class="regular-text frm-eap-full" type="text" name="${opt}[service_addresses][${idx}][state]" value="" placeholder="State" />
            </td>
            <td><input class="regular-text" type="text" name="${opt}[service_addresses][${idx}][zip]" value="" placeholder="ZIP" /></td>
            <td>
              <input class="regular-text" type="text" name="${opt}[service_addresses][${idx}][country]" value="US" placeholder="US" /><br/>
              <textarea class="regular-text frm-eap-full" rows="2" name="${opt}[service_addresses][${idx}][service_states]" placeholder=""></textarea><br/>
              <!-- NEW: Tags textarea -->
              <textarea class="regular-text frm-eap-full" rows="2" name="${opt}[service_addresses][${idx}][tags]" placeholder=""></textarea>
            </td>
            <td><button type="button" class="button frm-eap-del-row" aria-label="${del}">✕</button></td>
          </tr>`;
        tbody.insertAdjacentHTML('beforeend', tmpl);
        bindDeletes(addressesTable);
      };
    }
  
    bindDeletes(document);
  })();
  