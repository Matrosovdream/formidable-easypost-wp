<?php

if (!defined('ABSPATH')) { /* optional for WP projects */ }

final class FrmSmartyApi
{
    private const US_BASE   = 'https://us-street.api.smarty.com/street-address';
    private const INTL_BASE = 'https://international-street.api.smarty.com/verify';

    /** @var string */
    private string $authId = '';
    /** @var string */
    private string $authToken = '';

    /** @var bool Use POST with JSON body for US API */
    private bool $usePost = true;

    /** @var bool Toggle basic debug logging via error_log */
    private bool $logging = false;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * Read settings from WP options with constant overrides.
     * Option keys:
     *  - smarty_auth_id
     *  - smarty_auth_token
     */
    private function loadSettings(): void
    {
        $opts = function_exists('get_option') ? (array) get_option('frm_easypost', []) : [];

        $authId    = (string)($opts['smarty_auth_id']    ?? '');
        $authToken = (string)($opts['smarty_auth_token'] ?? '');

        if (defined('SMARTY_AUTH_ID')) {
            $authId = (string) constant('SMARTY_AUTH_ID');
        }
        if (defined('SMARTY_AUTH_TOKEN')) {
            $authToken = (string) constant('SMARTY_AUTH_TOKEN');
        }

        $this->authId    = $authId;
        $this->authToken = $authToken;
        $this->usePost   = true; // keep POST for US API
        $this->logging   = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Quick check to ensure credentials are set.
     */
    public function isConfigured(): bool
    {
        return $this->authId !== '' && $this->authToken !== '';
    }

    /**
     * Verify an address (US or International).
     * For US, set $strict=false to allow `match=enhanced` (requires license).
     *
     * @param array $address
     * @param bool  $strict
     * @return array{
     *   ok:bool,
     *   scope:'US'|'INTL',
     *   status:string,
     *   normalized?:array,
     *   candidates?:array,
     *   raw?:array,
     *   errors?:array
     * }
     */
    public function verifyAddress(array $address, bool $strict = true): array
    {
        if (!$this->isConfigured()) {
            // Attempt reload (in case options changed at runtime)
            $this->loadSettings();
        }
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'scope' => 'US',
                'status' => 'not_configured',
                'errors' => ['Smarty credentials are missing.'],
            ];
        }

        $country = strtoupper((string)($address['country'] ?? 'US'));
        $isUS    = ($country === '' || $country === 'US' || $country === 'USA' || $country === '840');

        return $isUS
            ? $this->verifyUS($address, $strict)
            : $this->verifyInternational($address);
    }

    // ---------------------------
    // Internals — US verification
    // ---------------------------

    private function verifyUS(array $a, bool $strict): array
    {
        $payload = [[
            'input_id'  => (string)($a['input_id'] ?? ''),
            'addressee' => (string)($a['name'] ?? $a['addressee'] ?? ''),
            'street'    => (string)($a['street'] ?? $a['street1'] ?? $a['street_line'] ?? ($a['street1'] ?? '')),
            'street2'   => (string)($a['street2'] ?? ''),
            'secondary' => (string)($a['secondary'] ?? ''),
            'city'      => (string)($a['city'] ?? ''),
            'state'     => (string)($a['state'] ?? ''),
            'zipcode'   => (string)($a['zipcode'] ?? $a['zip'] ?? ''),
            'candidates'=> (int)($a['candidates'] ?? 10),
            'match'     => $strict ? 'strict' : 'enhanced',
        ]];

        $url = self::US_BASE . '?' . http_build_query([
            'auth-id'    => $this->authId,
            'auth-token' => $this->authToken,
        ], '', '&', PHP_QUERY_RFC3986);

        $resp = $this->request($url, $this->usePost ? 'POST' : 'GET', $this->usePost ? $payload : null);
        if (!empty($resp['error'])) {
            return [
                'ok'      => false,
                'scope'   => 'US',
                'status'  => 'http_error',
                'errors'  => [$resp['error']],
            ];
        }

        $list = $resp['json'];
        if (!is_array($list)) {
            return [
                'ok'     => false,
                'scope'  => 'US',
                'status' => 'bad_response',
                'errors' => ['Unexpected response format from Smarty US Street API'],
            ];
        }

        $first = $list[0] ?? null;
        [$status, $deliverable] = $this->inferUsStatus($first);

        $normalized = $first ? [
            'delivery_line_1' => $first['delivery_line_1'] ?? null,
            'delivery_line_2' => $first['delivery_line_2'] ?? null,
            'last_line'       => $first['last_line'] ?? null,
            'components'      => $first['components'] ?? null,
            'metadata'        => $first['metadata'] ?? null,
            'analysis'        => $first['analysis'] ?? null,
            'deliverable'     => $deliverable,
        ] : null;

        return [
            'ok'         => true,
            'scope'      => 'US',
            'status'     => $status, // 'verified' | 'partial' | 'not_found' | 'non_postal'
            'normalized' => $normalized,
            'candidates' => $list,
            'raw'        => $list,
        ];
    }

    private function inferUsStatus($first): array
    {
        if (!$first) {
            return ['not_found', false];
        }

        $analysis = $first['analysis'] ?? [];
        $dpv      = $analysis['dpv_match_code'] ?? null; // Y,S,D => valid
        $vacant   = $analysis['dpv_vacant'] ?? null;     // N preferred
        $noStat   = $analysis['dpv_no_stat'] ?? null;    // N preferred
        $enhanced = $analysis['enhanced_match'] ?? '';

        if (is_string($enhanced) && stripos($enhanced, 'non-postal-match') !== false) {
            return ['non_postal', true];
        }

        $valid = in_array($dpv, ['Y','S','D'], true);
        $deliverable = $valid && ($vacant === 'N' || $vacant === null) && ($noStat === 'N' || $noStat === null);

        if ($valid) {
            return ['verified', $deliverable];
        }
        return ['partial', false];
    }

    // ------------------------------------
    // Internals — International verification
    // ------------------------------------

    private function verifyInternational(array $a): array
    {
        $q = [
            'auth-id'    => $this->authId,
            'auth-token' => $this->authToken,
            'country'    => (string)($a['country'] ?? ''),
        ];

        $hasFreeform = isset($a['freeform']) && trim((string)$a['freeform']) !== '';
        if ($hasFreeform) {
            $q['freeform'] = (string)$a['freeform'];
        } else {
            $q['address1']            = (string)($a['address1'] ?? $a['street1'] ?? $a['street'] ?? '');
            $q['address2']            = (string)($a['address2'] ?? $a['street2'] ?? '');
            $q['address3']            = (string)($a['address3'] ?? '');
            $q['address4']            = (string)($a['address4'] ?? '');
            $q['locality']            = (string)($a['locality'] ?? $a['city'] ?? '');
            $q['administrative_area'] = (string)($a['administrative_area'] ?? $a['state'] ?? '');
            $q['postal_code']         = (string)($a['postal_code'] ?? $a['zipcode'] ?? $a['zip'] ?? '');
            if (!empty($a['language'])) $q['language'] = (string)$a['language']; // 'native' or 'latin'
            if (array_key_exists('geocode', $a)) $q['geocode'] = $a['geocode'] ? 'true' : 'false';
        }

        $url  = self::INTL_BASE . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
        $resp = $this->request($url, 'GET', null);
        if (!empty($resp['error'])) {
            return [
                'ok'      => false,
                'scope'   => 'INTL',
                'status'  => 'http_error',
                'errors'  => [$resp['error']],
            ];
        }

        $list = $resp['json'];
        if (!is_array($list)) {
            return [
                'ok'     => false,
                'scope'  => 'INTL',
                'status' => 'bad_response',
                'errors' => ['Unexpected response format from Smarty International Street API'],
            ];
        }

        $first = $list[0] ?? null;
        $status = 'not_found';
        if ($first) {
            $analysis = $first['analysis'] ?? [];
            $vs = $analysis['verification_status'] ?? null; // 'Verified' | 'Partial' | 'Ambiguous' | etc.
            $status = $vs ? strtolower($vs) : 'partial';
        }

        $normalized = $first ? [
            'address1'   => $first['address1'] ?? null,
            'address2'   => $first['address2'] ?? null,
            'address3'   => $first['address3'] ?? null,
            'address4'   => $first['address4'] ?? null,
            'components' => $first['components'] ?? null,
            'metadata'   => $first['metadata'] ?? null,
            'analysis'   => $first['analysis'] ?? null,
        ] : null;

        return [
            'ok'         => true,
            'scope'      => 'INTL',
            'status'     => $status,
            'normalized' => $normalized,
            'candidates' => $list,
            'raw'        => $list,
        ];
    }

    // -------------
    // HTTP wrapper
    // -------------

    /**
     * @param string $url
     * @param 'GET'|'POST' $method
     * @param array<int,mixed>|null $jsonBody
     * @return array{error?:string,json?:mixed,status?:int}
     */
    private function request(string $url, string $method = 'GET', ?array $jsonBody = null): array
    {
        // Prefer WP HTTP API when available
        if (function_exists('wp_remote_request')) {
            $args = [
                'method'  => $method,
                'timeout' => 15,
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ];
            if ($method === 'POST' && $jsonBody !== null) {
                $args['body'] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $res = wp_remote_request($url, $args);
            if (is_wp_error($res)) {
                return ['error' => $res->get_error_message()];
            }
            $code = (int)wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);

            if ($this->logging) {
                error_log('[FrmSmartyApi] ' . $method . ' ' . $url . ' => ' . $code);
            }

            if ($code !== 200) {
                return ['error' => 'HTTP ' . $code . ' from Smarty', 'status' => $code];
            }
            $json = json_decode($body, true);
            return ['json' => $json, 'status' => $code];
        }

        // Fallback: cURL
        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ];
        if ($method === 'POST' && $jsonBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($this->logging) {
            error_log('[FrmSmartyApi] ' . $method . ' ' . $url . ' => ' . ($code ?: 'ERR') . ($err ? ' ' . $err : ''));
        }

        if ($err) {
            return ['error' => 'cURL error: ' . $err];
        }
        if ((int)$code !== 200) {
            return ['error' => 'HTTP ' . $code . ' from Smarty', 'status' => (int)$code];
        }
        $json = json_decode((string)$body, true);
        return ['json' => $json, 'status' => (int)$code];
    }
}
