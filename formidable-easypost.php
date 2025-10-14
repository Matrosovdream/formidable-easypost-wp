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

        //voidShipments();
        //saveEntryPdf();
        //streamOriginEntryPdf();
        //saveEntryPdf();
        //ApplyFormExtension();
        //voidShipment();
        //verifyAddressSmarty();
        createLabel();
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

    if( isset( $_GET['statuses'] ) ) {

        updateOldStatuses();
        die();

    }

}

function voidShipments() {

    $helper = new FrmEasypostEntryHelper();
    $helper->voidShipments();

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

    $entry_id = 17076;
    
    $applyForm = new FrmUpdateApplyForm();
    $applyForm->updateEntryStatus( $entry_id );

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
    $carrierHelper   = new FrmEasypostCarrierHelper();
    $carrierAccounts = $carrierHelper->getCarrierAccounts();

    $addresses = [
        "from_address" => [
            "company" => "EasyPost",
            "street1" => "11247 Tuxford St Unit B",
            "street2" => "4th Floor",
            "city"    => "Sun Valley",
            "state"   => "CA",
            "zip"     => "91352",
            "phone"   => "415-456-7890",
        ],
        "to_address" => [
            "name"    => "Dr. Steve Brule",
            "street1" => "1801 Columbia Road, NW Suite 200",
            "city"    => "Washington",
            "state"   => "DC",
            "zip"     => "20009",
            "phone"   => "310-808-5243",
        ],
    ];

    $rates     = [];
    $shipments = [];

    foreach ($carrierAccounts as $key => $account) {
        $labelData = [
            "from_address"     => $addresses['from_address'],
            "to_address"       => $addresses['to_address'],
            "parcel"           => [
                "weight"             => 5,
                "width"              => 9.5,
                "height"             => 2,
                "length"             => 12.5,
                "predefined_package" => $account['packages'][0] ?? null,
            ],
            "carrier_accounts" => [ $account['id'] ],
            'order_id'         => 2,
            'reference'        => 'Order #2',
        ];

        // Special case from your example
        if ($key === 2) {
            //$labelData['parcel']['predefined_package'] = 'FedExEnvelope';
        }

        $shipmentApi = new FrmEasypostShipmentApi();
        $shipment    = $shipmentApi->createShipment($labelData, false);

        // Attach some extra context for comparison
        $shipment['parcel']           = $labelData['parcel'];
        $shipment['carrier_accounts'] = $labelData['carrier_accounts'];

        $shipments[] = $shipment;

        if (isset($shipment['rates'])) {
            foreach ($shipment['rates'] as $rate) {
                $rate['shipment_id'] = $shipment['general']['id'] ?? null;
                $rate['package']     = $account['packages'][0] ?? null;
                $rates[] = $rate;
            }
        }
    }

    render_shipments_compare( $shipments );

    return $shipments;
}

/**
 * 2) Helper to render shipments in 3 columns with <pre> print_r output
 */
function render_shipments_compare(array $shipments): void {
    // Normalize to exactly 3 cells (pad with nulls if fewer)
    $cells = array_slice(array_pad($shipments, 3, null), 0, 3);

    echo '<style>
        .compare-table { width:100%; border-collapse:collapse; table-layout:fixed; }
        .compare-table td { vertical-align:top; border:1px solid #ddd; padding:8px; width:33.333%; }
        .compare-table pre { margin:0; white-space:pre-wrap; word-wrap:break-word; font-size:12px; }
        .compare-table .header { font-weight:600; margin-bottom:6px; display:block; }
    </style>';

    echo '<table class="compare-table"><tr>';
    foreach ($cells as $i => $item) {
        echo '<td>';
        echo '<span class="header">Shipment ' . ($i + 1) . '</span>';
        if ($item === null) {
            echo '<em>No data</em>';
        } else {
            // Capture print_r output and safely display it
            $dump = print_r($item, true);
            echo '<pre>' . htmlspecialchars($dump, ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        echo '</td>';
    }
    echo '</tr></table>';
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
    $res = $helper->updateShipmentsApi( ['pageSize' => 100] );

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


function updateOldStatuses() {

    $entry_ids = getOldEntries();

    //$entry_ids = array_slice( $entry_ids, 0, 1 );

    //print_r($entry_ids);

    foreach ( $entry_ids as $entry_id ) {
        // Update field 7 to "Complete-M"
        FrmEntryMeta::update_entry_meta( $entry_id, 7, null, 'Complete-M' );
    }

    echo 'ttt';
    echo count( $entry_ids); exit();

}

function getOldEntries() {

    return  [
        8776,     11576,     19126,     20497,     20472,     20033,     21261,     8199,     19332,     21549,
        20745,     21041,     22021,     21240,     19150,     16060,     13632,     20593,     14558,     19604,
        18255,     19052,     18498,     21050,     12347,     19669,     15760,     20119,     17428,     19262,
        19559,     10188,     17635,     8641,     19281,     17136,     13344,     20110,     13741,     17281,
        17364,     19395,     17774,     15763,     15527,     18911,     16023,     19862,     19550,     19945,
        20043,     19784,     15327,     7441,     18537,     17009,     11424,     16271,     17790,     19991,
        11624,     18743,     19442,     18154,     18716,     19241,     19204,     18468,     18514,     16592,
        18951,     16796,     18692,     19666,     19583,     18943,     18216,     17847,     19196,     17747,
        15125,     19365,     19684,     19634,     18361,     18021,     18168,     19158,     19056,     18172,
        18152,     1866,     18250,     17904,     17724,     18467,     16873,     17997,     19981,     18231,
        18261,     17915,     19151,     9816,     14139,     10989,     2643,     17554,     18387,     18245,
        19558,     18405,     18373,     18088,     15761,     19107,     18151,     19723,     19359,     15940,
        17094,     17097,     17398,     17379,     16416,     17078,     18658,     12209,     17861,     1944,
        17929,     19405,     16741,     16810,     14265,     16788,     18008,     16661,     17400,     16078,
        16708,     14973,     14103,     16696,     17501,     17840,     17583,     18237,     17892,     17875,
        17653,     17634,     16804,     17698,     17749,     17860,     10915,     12072,     16418,     14935,
        4227,     520,     16596,     4434,     13289,     9071,     13841,     12877,     16507,     11350,
        12083,     16569,     16537,     16479,     10316,     10068,     15220,     16782,     16763,     12480,
        16830,     16528,     17021,     16786,     13130,     14086,     16793,     16520,     17276,     10358,
        17337,     14799,     16290,     16954,     16090,     16030,     16235,     16262,     16009,     16021,
        16124,     16319,     16358,     9495,     16310,     15918,     15782,     15970,     14844,     16445,
        16476,     15967,     16209,     13897,     10709,     14796,     15570,     15844,     7054,     15944,
        15819,     12455,     14923,     15571,     16288,     16177,     16108,     15859,     15595,     15804,
        13039,     11139,     15646,     14736,     15813,     15357,     15347,     15392,     15420,     2290,
        15154,     15517,     15663,     14102,     15318,     14993,     14632,     12739,     12263,     14291,
        14339,     15440,     13012,     12082,     11277,     14053,     14967,     14994,     10632,     14996,
        14047,     14184,     14762,     12835,     14288,     14282,     14158,     12763,     13210,     12627,
        13286,     12719,     13659,     13757,     13868,     14007,     14113,     9286,     14805,     7345,
        4484,     3869,     4101,     13556,     11067,     4005,     14097,     13827,     15003,     15191,
        8864,     6888,     12251,     13006,     13105,     5948,     10636,     13150,     10451,     13600,
        13526,     5310,     12770,     1409,     9706,     6654,     13487,     13014,     13524,     13744,
        10417,     11849,     9292,     3507,     13620,     13307,     13369,     13336,     13418,     13606,
        13738,     13751,     13847,     13864,     14315,     13875,     14341,     14355,     14401,     12728,
        12429,     14418,     12191,     12294,     5821,     12222,     12268,     8303,     3955,     11233,
        12520,     12184,     9484,     9886,     12307,     12255,     12357,     3351,     9524,     12766,
        12669,     12778,     9995,     2540,     12661,     10530,     10414,     10615,     11329,     10643,
        10854,     11071,     11075,     11393,     9088,     12925,     12207,     8042,     11521,     11653,
        11816,     12068,     12573,     13000,     10678,     10691,     13034,     6491,     6237,     13095,
        10306,     11448,     11342,     11267,     12653,     12903,     12950,     10605,     8499,     9228,
        8726,     11337,     11100,     6568,     12379,     11185,     9474,     8702,     4643,     6709,
        383,     6356,     5941,     3445,     5787,     9166,     11240,     6300,     11926,     11765,
        12428,     11421,     10597,     11688,     12378,     10201,     12424,     11055,     11706,     11497,
        11678,     10825,     3237,     11470,     9961,     9779,     2770,     6969,     9953,     9322,
        9312,     9294,     8614,     10625,     8253,     8274,     10302,     8421,     7464,     8975,
        10031,     10145,     9632,     1759,     9701,     9635,     7530,     6940,     4085,     9194,
        9557,     10450,     9034,     10484,     10533,     10487,     10762,     10768,     10828,     10066,
        9665,     11121,     9078,     9090,     9339,     10590,     1778,     10049,     8155,     9471,
        9531,     7706,     9963,     9786,     9480,     9540,     10568,     11118,     9111,     8772,
        8500,     8332,     8854,     8350,     8632,     7713,     8827,     8851,     8277,     8473,
        8520,     8910,     9232,     8313,     7760,     8259,     8229,     7987,     7938,     7681,
        7616,     7580,     7560,     8183,     8188,     6119,     6150,     6236,     7447,     7472,
        7497,     6417,     4238,     9512,     9340,     9864,     8413,     8843,     9200,     9682,
        6769,     4572,     6952,     7677,     6339,     6055,     4959,     2605,     3654,     4605,
        1815,     5505,     6899,     7411,     7389,     7350,     7082,     7611,     7006,     6986,
        6907,     7536,     7630,     7793,     7091,     4012,     6939,     6925,     6475,     4627,
        8145,     3427,     6302,     4914,     5154,     5042,     6465,     6537,     6428,     6651,
        6611,     6602,     6794,     5474,     7064,     6242,     6232,     5692,     5073,     7035,
        7079,     2241,     6963,     6706,     7016,     6098,     6200,     6210,     5959,     3602,
        6735,     5225,     6347,     6141,     6015,     5825,     5675,     5191,     6037,     6380,
        5858,     4230,     5076,     6174,     3701,     5203,     5784,     5598,     5043,     5180,
        5977,     5716,     2178,     5698,     5380,     5926,     5782,     5094,     4124,     3870,
        3511,     3720,     2953,     1524,     2704,     3610,     3159,     3979,     4206,     3995,
        4169,     2456,     4215,     2363,     3195,     2362,     2616,     3413,     3807,     3547,
        3331,     3144,     2989,     2380,     4022,     1408,     3515,     3797,     2904,     2625,
        3261,     3894,     2905,     3269,     3429,     3310,     2420,     2930,     472,     1095,
        2037,     2040,     946,     820,     2080,     2090,     1158,     970,     993,     1001,
        2107,     1003,     2116,     1182,     1241,     1035,     1058,     2181,     2199,     2210,
        1300,     1080,     827,     2250,     1390,     2264,     856,     1413,     2279,     1899,
        2305,     1429,     876,     2383,     887,     2382,     893,     2472,     921,     941,
        2477,     948,     1498,     1517,     1543,     1542,     2549,     1621,     2645,     2655,
        1636,     1641,     1674,     2733,     1639,     2724,     1737,     565,     2772,     2825,
        2887,     2873,     1906,     1909,     2915,     2946,     1418,     1952,     1748
    ];

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


