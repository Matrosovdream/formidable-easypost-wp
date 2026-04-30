<?php

class FrmEasypostCarrierHelper {

    public function __construct() {

    }

    public function getCarrierAccounts(): array {
        $opts = get_option( 'frm_easypost', [] );
    
        $rows = isset($opts['carrier_accounts']) && is_array($opts['carrier_accounts'])
            ? $opts['carrier_accounts']
            : [];
    
        $accounts = [];
    
        foreach ( $rows as $row ) {
            $code = isset($row['code']) ? (string)$row['code'] : '';
            $id   = isset($row['id']) ? (string)$row['id'] : '';
            $pkgS = isset($row['packages']) ? (string)$row['packages'] : '';
    
            if ($code === '' || $id === '') {
                continue; // skip incomplete rows
            }
    
            $packages = array_values( array_filter( array_map( 'trim', explode( ',', $pkgS ) ) ) );
    
            $accounts[] = [
                // keep "title" like before; derive from code for backward-compatibility
                'title'    => strtoupper($code),
                'id'       => $id,
                'packages' => $packages,
                // optional: expose the code too
                'code'     => $code,
            ];
        }
    
        return $accounts;
    }

    /**
     * Fetch the full predefined_packages list from EasyPost metadata.
     *
     * Returns: [ ['code' => 'usps', 'title' => 'USPS', 'packages' => ['Parcel', 'Card', ...]], ... ]
     * Cached per-carrier-set in a transient for 24h.
     */
    public function getPredefinedPackages( array $carrierCodes = ['usps', 'fedex'] ): array {

        $carrierCodes = array_values( array_unique( array_map( 'strtolower', array_filter( $carrierCodes ) ) ) );
        sort( $carrierCodes );

        $cacheKey = 'frm_ep_predef_pkgs_' . md5( implode( ',', $carrierCodes ) );
        $cached   = get_transient( $cacheKey );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        try {
            $api  = new FrmEasypostShipmentApi();
            $data = $api->getPredefinedPackages( $carrierCodes );
        } catch ( \Throwable $e ) {
            return [];
        }

        $out = [];
        foreach ( (array) $data as $carrier ) {

            $code  = strtolower( $carrier['name'] ?? '' );
            $title = $carrier['human_readable'] ?? strtoupper( $code );

            if ( $code === '' ) {
                continue;
            }

            // Walk all services -> predefined_packages, dedupe by name.
            $names = [];
            $services = $carrier['services'] ?? [];
            foreach ( (array) $services as $svc ) {
                $packages = $svc['predefined_packages'] ?? [];
                foreach ( (array) $packages as $pkg ) {
                    $name = $pkg['name'] ?? '';
                    if ( $name !== '' ) {
                        $names[ $name ] = true;
                    }
                }
            }

            // Some metadata payloads also expose a top-level predefined_packages array
            $top = $carrier['predefined_packages'] ?? [];
            foreach ( (array) $top as $pkg ) {
                $name = is_array( $pkg ) ? ( $pkg['name'] ?? '' ) : (string) $pkg;
                if ( $name !== '' ) {
                    $names[ $name ] = true;
                }
            }

            $packages = array_keys( $names );
            sort( $packages );

            $out[] = [
                'code'     => $code,
                'title'    => $title,
                'packages' => $packages,
            ];
        }

        set_transient( $cacheKey, $out, DAY_IN_SECONDS );

        return $out;
    }

}