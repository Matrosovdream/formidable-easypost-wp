<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class FrmEasypostAbstractApi {

    protected $client;

    public function __construct( array $overrides = [] ) {

        $this->configClient();

    }

    protected function configClient() {

        require_once(FRM_EAP_BASE_URL."/vendor/autoload.php");

        // Pull saved plugin settings if they exist
        $cfg = get_option( 'frm_easypost', [] );
        $apiKey    = $cfg['api_key'];

        $this->client = new \EasyPost\EasyPostClient( $apiKey );

    }

    protected function handleErrors( object $response ) {

        $messages = [];

        if (!empty($response->messages)) {
            foreach ($response->messages as $m) {
                $messages[] = $m->code . ": " . $m->message;
            }
        }

        return $messages;

    }

}

// --- Optional convenience factory ---
if ( ! function_exists( 'frm_shipstation' ) ) {
    /**
     * Get a shared instance with settings loaded from options/constants.
     * @return FrmShipstationApi
     */
    function frm_shipstation(): FrmShipstationApi {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new FrmShipstationApi();
        }
        return $instance;
    }
}
