<?php

const FRM_EP_ENTRY_ADDRESS_FIELDS = [
    'firstname' => 1,
    'lastname'  => 2,
    'street1'  => 37,
    'street2'  => 38,
    'city'      => 39,
    'state'     => 40,
    'zip'       => 41,
    'phone'     => 248,
];

const FRM_EP_PROC_TIME_FIELDS = [
    'standard' => [
        'id' => 145,
        'label' => 'Standard',
    ],
    'expedited' => [
        'id' => 175,
        'label' => 'Expedited',
    ],
    'rushed' => [
        'id' => 375,
        'label' => 'Rushed',
    ]
];