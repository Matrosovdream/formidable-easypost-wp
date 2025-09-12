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

// Initialize core
require_once 'classes/FrmEasypostInit.php';


add_action('init', 'FrmEasypostInit');
function FrmEasypostInit() {
    
    if( isset( $_GET['logg'] ) ) {

        require_once(FRM_EAP_BASE_URL."/vendor/autoload.php");

        $client = new \EasyPost\EasyPostClient( 'EZTK406afddc66a44d46bfb3e899b65f2263eNwnpyGnaVx3aDwYtPX6LA' );

        $shipment = $client->shipment->create([
            "from_address" => [
                "company" => "EasyPost",
                "street1" => "118 2nd Street",
                "street2" => "4th Floor",
                "city"    => "San Francisco",
                "state"   => "CA",
                "zip"     => "94105",
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
            "parcel" => [
                "length" => 20.2,
                "width"  => 10.9,
                "height" => 5,
                "weight" => 65.9,
            ],
        ]);

        //$boughtShipment = $client->shipment->buy($shipment->id, $shipment->lowestRate());

        echo $boughtShipment;


        $rates = [];

        echo '<pre>';
        print_r($shipment->getParcel());
        echo '</pre>';
        die();

    }

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


