<?php
class FrmEasypostInit {

    public function __construct() {

        // Admin classes
        require_once FRM_EAP_BASE_URL.'/classes/admin/FrmEasypostAdminSettings.php';

        // API class
        $this->include_api();

        /*
        // Endpoints
        require_once FRM_EAP_BASE_URL.'/classes/endpoints/FrmEasypostRoutes.php';

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Utilities
        $this->include_utils();

        // Helpers
        $this->include_helpers();

        // CRON
        $this->include_cron();

        // Hooks
        $this->include_hooks();

        /*
        // Shortcodes
        $this->include_shortcodes();
        */

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

    }

    private function include_models() {

        // Abstract model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostAbstractModel.php';

        // Order model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostOrderModel.php';

        // Shipment model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostShipmentModel.php';

        // Carrier model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostCarrierModel.php';

        // Package model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostPackageModel.php';

        // Service model
        require_once FRM_EAP_BASE_URL.'/classes/models/FrmEasypostServiceModel.php';

    }

    private function include_utils() {

    }

    private function include_helpers() {

        // Order Helper
        require_once FRM_EAP_BASE_URL.'/classes/helpers/FrmEasypostOrderHelper.php';

        // Carrier Helper
        require_once FRM_EAP_BASE_URL.'/classes/helpers/FrmEasypostCarrierHelper.php';

        // Shipment Helper
        require_once FRM_EAP_BASE_URL.'/classes/helpers/FrmEasypostShipmentHelper.php';

    }

    private function include_cron() {

        // Orders cron
        require_once FRM_EAP_BASE_URL.'/classes/cron/FrmEasypostOrdersCron.php';
        FrmEasypostOrdersCron::init();

        // Carriers cron
        require_once FRM_EAP_BASE_URL.'/classes/cron/FrmEasypostCarriersCron.php';
        FrmEasypostCarriersCron::init();

        // Shipments cron
        require_once FRM_EAP_BASE_URL.'/classes/cron/FrmEasypostShipmentsCron.php';
        FrmEasypostShipmentsCron::init();

    }

    private function include_shortcodes() {

        // Refund
        require_once FRM_EAP_BASE_URL.'/shortcodes/payment.refund.php';


    }

    private function include_hooks() {
        
        // Void shipment ajax
        require_once FRM_EAP_BASE_URL.'/actions//user/void-shipment.php';

    }

}

new FrmEasypostInit();