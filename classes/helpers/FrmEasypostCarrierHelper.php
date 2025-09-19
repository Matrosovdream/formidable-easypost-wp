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

}