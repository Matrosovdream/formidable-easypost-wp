<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class FrmEasypostAbstractApi {

    protected $client;
    protected $apiKey;

    public function __construct( array $overrides = [] ) {

        $this->configClient();

    }

    protected function configClient() {

        require_once(FRM_EAP_BASE_URL."/vendor/autoload.php");

        // Pull saved plugin settings if they exist
        $cfg = get_option( 'frm_easypost', [] );
        $apiKey    = $cfg['api_key'];
        $this->apiKey = $apiKey;

        $this->client = new \EasyPost\EasyPostClient( $apiKey );

    }

    protected function handleErrors(object $response): array
    {
        $messages = [];

        // Filter out common server/HTTP error words
        $filterWords = 'Bad Request|Unauthorized|Payment Required|Forbidden|Not Found|';
    
        if (!empty($response->messages)) {
            foreach ($response->messages as $m) {
                $msg = trim($m->code . ": " . $m->message);
    
                // Filter out server/HTTP errors like "400 Bad Request", "500 Internal Server Error", etc.
                if (preg_match('/\b\d{3}\s+('.$filterWords.')/i', $msg)) {
                    continue;
                }
    
                $messages[] = $msg;
            }
        }
    
        return $messages;
    }    

    /** @param mixed $errors */
    protected function normalizeEasyPostErrors(mixed $errors): array
    {
        $out = [];
        $walk = function ($item) use (&$walk, &$out) {
            if (is_array($item)) {
                foreach ($item as $v) { $walk($v); }
                return;
            }
            if (is_object($item)) {
                $out[] = array_filter([
                    'field'      => property_exists($item, 'field') ? $item->field : null,
                    'message'    => property_exists($item, 'message') ? $item->message : (string)json_encode($item),
                    'suggestion' => property_exists($item, 'suggestion') ? $item->suggestion : null,
                ], fn($v) => $v !== null);
                return;
            }
            if (is_string($item)) {
                $out[] = ['message' => $item];
                return;
            }
            if ($item !== null) {
                $out[] = ['message' => (string)$item];
            }
        };
        $walk($errors);
        return $out;
    }

    protected function formatEasyPostException(ApiException $e): array
    {
        // Many EasyPost exceptions expose getErrors() with detailed field errors
        $errs = method_exists($e, 'getErrors') ? ($e->getErrors() ?? []) : [];
        return [
            'ok'     => false,
            'errors' => $this->normalizeEasyPostErrors($errs) ?: [['message' => $e->getMessage()]],
            'raw'    => (object)[
                'status'  => method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : null,
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
            ],
        ];
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
