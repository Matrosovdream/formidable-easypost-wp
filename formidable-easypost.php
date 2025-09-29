<?php
/*
Plugin Name: Formidable forms Extension - EasyPost API
Description: 
Version: 1.0
Plugin URI: 
Author URI: 
Author: Stanislav Matrosov
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables
define('FRM_EAP_BASE_URL', __DIR__);
define('FRM_EAP_BASE_PATH', plugin_dir_url(__FILE__));

// Initialize core
require_once 'classes/FrmEasypostInit.php';


add_action('init', 'FrmEasypostInit');
function FrmEasypostInit() {
    
    if( isset( $_GET['logg'] ) ) {

        //saveEntryPdf();
        streamOriginEntryPdf();
        //saveEntryPdf();
        //ApplyFormExtension();
        //voidShipment();
        //verifyAddressSmarty();
        //createLabel();
        //getShipments();
        //getPredefinedPackages();
        //verifyAddress();
        //getShipmentModel();
        //updateShipmentsApi();
        //getAddressesApi();
        //getEntryAddresses();
        //getCarrierAccounts();

        die();

    }

}


function saveEntryPdf() {

    $entry_id = 17076;

    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->saveEntryPdf( $entry_id );

}

function streamOriginEntryPdf() {

    $entry_id = 17076;

    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->streamOriginEntryPdf( $entry_id );

}

function streamEntryPdf() {

    $entry_id = 17076;

    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->streamTmpEntryPdf( $entry_id );

}


function ApplyFormExtension() {
    
    $applyForm = new FrmUpdateApplyForm();
    $applyForm->updateAllEntries();

}

function voidShipment() {

    $shp = 'shp_7c3cd986f0c548b481173758b0b0dd1b';

    $shipmentApi = new FrmEasypostShipmentApi();
    $res = $shipmentApi->refundShipment($shp);

    echo '<pre>';
    print_r($res);
    echo '</pre>';

    die();

}

function verifyAddressSmarty() {

    $addressData = [
        'name'    => 'Jane Doe Test',
        'street1' => '1600 Amphitheatre Pkwy',
        'city'    => 'Mountain View',
        'state'   => 'CA',
        'zipcode' => '94043',
        'country' => 'US',
    ];

    $smartyApi = new FrmSmartyApi();
    $address = $smartyApi->verifyAddress($addressData, $strict=true);

    echo '<pre>';
    print_r($address);
    echo '</pre>';

    die();

}

function getCarrierAccounts() {

    $shipmentApi = new FrmEasypostShipmentApi();
    $accounts = $shipmentApi->getCarrierAccounts();

    echo '<pre>';
    print_r($accounts);
    echo '</pre>';

    die();

}

function createLabel() {

    $carrierHelper = new FrmEasypostCarrierHelper();
    $carrierAccounts = $carrierHelper->getCarrierAccounts();

    $addresses = [
        "from_address" => [
            "company" => "EasyPost",
            "street1" => "118 2nd Street",
            "street2" => "4th Floor",
            "city"    => "San Francisco",
            "state"   => "CA",
            "zip"     => "94101",
            "phone"   => "415-456-7890",
        ],
        "to_address" => [
            "name"    => "Dr. Steve Brule",
            "street1" => "179 N Harbor Dr",
            "city"    => "Redondo Beach",
            "state"   => "CA",
            "zip"     => "90277",
            "phone"   => "310-808-5243",
        ],
    ];

    $rates = [];
    foreach( $carrierAccounts as $account ) {
        
        $labelData = [
            "from_address" => $addresses['from_address'],
            "to_address"   => $addresses['to_address'],
            "parcel" => [
                "weight" => 1.0,
                //"predefined_package" => $account['packages'][0],
            ],
            //"carrier_accounts" => [$account['id']],
            'order_id' => 2,
            'reference' => 'Order #2',
        ];

        $shipmentApi = new FrmEasypostShipmentApi();
        $shipment = $shipmentApi->createShipment($labelData);

        if( isset( $shipment['rates'] ) ) {

            foreach( $shipment['rates'] as $rate ) {

                $rate['shipment_id'] = $shipment['general']['id'];
                $rate['package'] = $account['packages'][0];
                $rates[] = $rate;
            }

        }

    }

    //$boughtShipment = $shipmentApi->buyLabel($shipment->id, $shipment->lowestRate());

    echo '<pre>';
    print_r($shipment);
    echo '</pre>';

}

function getEntryAddresses() {

    $entry_id = 114362;
    $model = new FrmEasypostEntryHelper();
    $addresses = $model->getEntryAddresses($entry_id);

    echo '<pre>';
    print_r($addresses);
    echo '</pre>';

    die();

}    

function getAddressesApi() {

    $addressApi = new FrmEasypostAddressApi();
    $addresses = $addressApi->getAllAddresses(100);

    echo '<pre>';
    print_r($addresses);
    echo '</pre>';

    die();

}

function getShipmentModel() {

    $model = new FrmEasypostShipmentModel();
    $shipment = $model->getAllByEntryId(125);

    echo '<pre>';
    print_r($shipment);
    echo '</pre>';

    die();

}

function updateShipmentsApi() {

    $helper = new FrmEasypostShipmentHelper();
    $res = $helper->updateShipmentsApi( ['pageSize' => 1000] );

    echo '<pre>';
    print_r($res);
    echo '</pre>';

    die();

}

function verifyAddress() {

    $addressData = [
        'name'    => 'Jane Doe123',
        'street1' => '388 Townsend St',
        'street2' => 'Apt 20',
        'city'    => 'San Francisco',
        'state'   => 'CA',
        'zip'     => '94107',
        'country' => 'US',
    ];

    $addressApi = new FrmEasypostAddressApi();
    $address = $addressApi->verifyAddress($addressData, $strict=false);

    echo '<pre>';
    print_r($address);
    echo '</pre>';

    die();

}

function getPredefinedPackages() {

    $shipmentApi = new FrmEasypostShipmentApi();
    $packages = $shipmentApi->getPredefinedPackages(['USPS', 'UPS']);

    echo '<pre>';
    print_r($packages);
    echo '</pre>';

    die();

}

function refundShipment() {

    $shipmentApi = new FrmEasypostShipmentApi();
    $refunded = $shipmentApi->refundShipment('shp_c29dc6ec03d54e0799b445fb8c93dc50');

    echo '<pre>';
    print_r($refunded);
    echo '</pre>';

    die();

}


function getShipments() {

    $shipmentApi = new FrmEasypostShipmentApi();
    $shipments = $shipmentApi->getAllShipments(1000);

    echo '<pre>';
    print_r($shipments);
    echo '</pre>';

    die();

}











































function shp_orders_format_date($val) {
    if (empty($val) || $val === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($val);
    if (!$ts) return esc_html($val);
    return esc_html(date_i18n('Y-m-d H:i', $ts));
}


// ===== Register assets (once) =====
add_action('wp_enqueue_scripts', function () {
    $base = plugin_dir_path(__FILE__);
    $url  = plugin_dir_url(__FILE__);

    // Version by filemtime for cache-busting during dev
    $css_file = $base . 'assets/CSS/frm-shipstation.css';
    $js_file  = $base . 'assets/JS/frm-shipstation-shortcodes.js';

    $css_ver = file_exists($css_file) ? filemtime($css_file) : '1.0.0';
    $js_ver  = file_exists($js_file)  ? filemtime($js_file)  : '1.0.0';

    wp_register_style(
        'frm-shipstation',
        $url . 'assets/CSS/frm-shipstation.css?time=' . time(),
        [],
        $css_ver
    );

    wp_register_script(
        'frm-shipstation-shortcodes',
        $url . 'assets/JS/frm-shipstation-shortcodes.js?time=' . time(),
        [],
        $js_ver,
        true
    );
});


