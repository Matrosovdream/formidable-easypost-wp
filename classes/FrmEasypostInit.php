<?php
class FrmEasypostInit {

    public function __construct() {

        // Admin classes
        require_once FRM_EAP_BASE_URL.'/classes/admin/FrmEasypostAdminSettings.php';

        // API class
        $this->include_api();

        // Shortcodes
        $this->include_shortcodes();

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Helpers
        $this->include_helpers();

        // CRON
        $this->include_cron();

    }

    private function include_migrations() {

        // Entries cleaner extra tables
        require_once FRM_EAP_BASE_URL.'/classes//migrations/FrmEasypostMigrations.php';

        // Run migrations
        FrmEasypostMigrations::maybe_upgrade();

    }

    private function include_api() {

        // Abstract API
        require_once FRM_EAP_BASE_URL.'/classes/api/FrmEasypostAbstractApi.php';

        // Shipment API
        require_once FRM_EAP_BASE_URL.'/classes/api/FrmEasypostShipmentApi.php';

        // Address API
        require_once FRM_EAP_BASE_URL.'/classes/api/FrmEasypostAddressApi.php';

        // Smarty API
        require_once FRM_EAP_BASE_URL.'/classes/api/Smarty/FrmSmartyApi.php';

    }

    private function include_models() {

        // Abstract model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostAbstractModel.php';

        // Shipment model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentModel.php';

        // Shipment Address model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentAddressModel.php';

        // Shipment Parcel model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentParcelModel.php';

        // Shipment Label model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentLabelModel.php';

        // Shipment Rate model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentRateModel.php';

        // Shipment Address corporate
        //require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentAddressCorpModel.php';

    }

    private function include_utils() {

    }

    private function include_helpers() {

        // Shipment Helper
        require_once FRM_EAP_BASE_URL.'/classes/helpers/FrmEasypostShipmentHelper.php';

        // Entry Helper
        require_once FRM_EAP_BASE_URL.'/classes/helpers/FrmEasypostEntryHelper.php';

        // Carrier Helper
        require_once FRM_EAP_BASE_URL.'/classes/helpers/FrmEasypostCarrierHelper.php';

    }

    private function include_cron() {

        // Shipments cron
        require_once FRM_EAP_BASE_URL.'/classes/cron/FrmEasypostShipmentsCron.php';
        FrmEasypostShipmentsCron::init();

    }

    private function include_shortcodes() {

        // Refund
        require_once FRM_EAP_BASE_URL.'/shortcodes/admin/create-easypost-shipment.php';

        // List Shipments for an entry
        require_once FRM_EAP_BASE_URL.'/shortcodes/admin/entry-shipments.php';

    }

    private function include_hooks() {
        
        // Void shipment ajax
        require_once FRM_EAP_BASE_URL.'/actions//user/void-shipment.php';

    }

}

new FrmEasypostInit();