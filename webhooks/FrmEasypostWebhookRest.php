<?php
/**
 * Plugin Name: EasyPost Webhook (Payload Logger)
 * Description: Logs payloads from /wp-json/easypost/v1/easypost-update with datetime into wp-content/easypost-webhook-logs.log
 * Version:     1.1.0
 */

if ( ! defined('ABSPATH') ) { exit; }

final class FrmEasypostWebhookRest {

    private const NS           = 'easypost/v1';
    private const ROUTE        = 'easypost-update';
    private const LOG_FILENAME = 'easypost-webhook-logs.log';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_route']);
    }

    public static function register_route(): void {
        register_rest_route(
            self::NS,
            '/' . self::ROUTE,
            [
                'methods'             => 'GET,POST',
                'callback'            => [__CLASS__, 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public static function handle_webhook(\WP_REST_Request $request): \WP_REST_Response {
        $raw        = $request->get_body() ?? '';
        $content_ty = $request->get_header('content-type') ?? '';

        // Parse payload only
        if (stripos($content_ty, 'application/json') !== false) {
            $payload = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $payload = ['_raw' => $raw];
            }
        } elseif (stripos($content_ty, 'application/x-www-form-urlencoded') !== false) {
            $payload = $request->get_body_params();
        } else {
            $try = json_decode($raw, true);
            $payload = (json_last_error() === JSON_ERROR_NONE) ? $try : ['_raw' => $raw];
        }

        // Process payload
        self::process_payload($payload);

        // Save payload with timestamp
        self::append_log_line($payload);

        return new \WP_REST_Response(['ok' => true], 200);
    }

    private static function process_payload($payload) {
        
        $event = $payload['description'] ?? null;
        $result = $payload['result'] ?? null;

        if ( ! $event || ! $result ) {
            return;
        }

        switch ($event) {
            case 'tracker.updated':
                self::update_shipment($result);
                break;
            case 'tracker.created':
                self::update_shipment($result);
                break;
            default:
                // Handle unknown event
                break;
        }

    }

    private static function update_shipment( array $data ) {
        
        $shipmenId = $data['shipment_id'] ?? null;
        if ( ! $shipmenId ) { return; }

        $shipmentHelper = new FrmEasypostShipmentHelper();
        $shipmentHelper->updateShipmentApi( $shipmenId );

    }

    private static function log_path(): string {
        return trailingslashit(WP_CONTENT_DIR) . self::LOG_FILENAME;
    }

    private static function append_log_line($payload): void {
        $path = self::log_path();
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            @chmod($dir, 0700);
        }

        $fh = @fopen($path, 'ab');
        if (!$fh) { return; }

        // DateTime: use WP timezone setting
        $dt = new \DateTime('now', wp_timezone());
        $timestamp = $dt->format('Y-m-d H:i:sP');

        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        $line = sprintf("[%s] %s", $timestamp, $json);

        @fwrite($fh, $line . PHP_EOL);
        @fclose($fh);
        @chmod($path, 0600);
    }
}

FrmEasypostWebhookRest::init();
