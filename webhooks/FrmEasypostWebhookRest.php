<?php
/**
 * Plugin Name: EasyPost Webhook (Payload Logger)
 * Description: Logs payloads from /wp-json/easypost/v1/easypost-update with datetime into wp-content/easypost-webhook-logs.log. Shipment updates are processed via WP-Cron single events.
 * Version:     1.2.0
 */

if ( ! defined('ABSPATH') ) { exit; }

final class FrmEasypostWebhookRest {

    private const NS           = 'easypost/v1';
    private const ROUTE        = 'easypost-update';
    private const LOG_FILENAME = 'easypost-webhook-logs.log';

    /** Default delay for ALL scheduled single events (seconds) */
    private const DEFAULT_DELAY_SECONDS = 3;

    /** If updateShipmentApi() returns null, retry this single event after N seconds */
    private const RETRY_DELAY_SECONDS = 2;

    /** Safety cap to avoid infinite retries */
    private const MAX_RETRIES = 10;

    /** Cron hook name */
    private const CRON_HOOK = 'frm_easypost_process_shipment_update';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_route']);

        // Cron worker for shipment updates
        add_action(self::CRON_HOOK, [__CLASS__, 'cron_process_shipment_update'], 10, 2);
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

        // Process payload (now schedules cron events instead of doing work immediately)
        self::process_payload($payload);

        // Save payload with timestamp
        self::append_log_line($payload);

        return new \WP_REST_Response(['ok' => true], 200);
    }

    private static function process_payload($payload): void {
        $event  = $payload['description'] ?? null;
        $result = $payload['result'] ?? null;

        if ( ! $event || ! is_array($result) ) {
            return;
        }

        switch ($event) {
            case 'tracker.updated':
            case 'tracker.created':
                self::update_shipment($result);
                break;
            default:
                // ignore other events
                break;
        }
    }

    /**
     * Instead of doing shipment update immediately:
     * 1) schedule a single cron event (+N seconds, default 3)
     * 2) event will run cron_process_shipment_update()
     */
    private static function update_shipment(array $data): void {
        $shipmentId = $data['shipment_id'] ?? null;
        if ( ! $shipmentId ) { return; }

        $delay = (int) apply_filters('frm_easypost_webhook_default_delay_seconds', self::DEFAULT_DELAY_SECONDS);
        if ($delay < 0) { $delay = 0; }

        self::schedule_shipment_update((string) $shipmentId, $delay, 0);
    }

    /**
     * Cron callback: runs updateShipmentApi() and reschedules itself if null
     *
     * @param string $shipmentId
     * @param int    $attempt
     */
    public static function cron_process_shipment_update($shipmentId, $attempt = 0): void {
        $shipmentId = (string) $shipmentId;
        $attempt    = is_numeric($attempt) ? (int) $attempt : 0;

        if ($shipmentId === '') {
            return;
        }

        // Run the real work here
        $shipmentHelper = new FrmEasypostShipmentHelper();

        // If updateShipmentApi returns null => retry in 2 seconds
        $res = $shipmentHelper->updateShipmentApi($shipmentId);

        if ($res === null) {
            if ($attempt >= self::MAX_RETRIES) {
                // optional: log that we gave up
                self::append_log_line([
                    '_type'      => 'shipment_update_gave_up',
                    'shipment_id'=> $shipmentId,
                    'attempt'    => $attempt,
                    'reason'     => 'updateShipmentApi returned null too many times',
                ]);
                return;
            }

            $retryDelay = (int) apply_filters('frm_easypost_webhook_retry_delay_seconds', self::RETRY_DELAY_SECONDS);
            if ($retryDelay < 1) { $retryDelay = 1; }

            self::schedule_shipment_update($shipmentId, $retryDelay, $attempt + 1);
        }
    }

    /**
     * Schedule a single WP-Cron event for shipment update.
     * Requirement: "All these single cron events should be run +n seconds, default 3"
     */
    private static function schedule_shipment_update(string $shipmentId, int $delaySeconds, int $attempt): void {
        $runAt = time() + max(0, $delaySeconds);

        // NOTE: We intentionally do NOT dedupe by shipment id, because you said:
        // "All these single cron events should be run"
        // If you ever want dedupe, you can add a wp_next_scheduled() guard here.

        wp_schedule_single_event(
            $runAt,
            self::CRON_HOOK,
            [$shipmentId, $attempt]
        );
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
