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

// closest_address, entry_address, passport_service
const FRM_EP_LABEL_DIRECTION_TYPES = [
    'national_passport' => [
      'label' => 'National Passport',
      'addresses' => [
        'from' => 'entry_address',
        'to'   => 'closest_address',
      ]
    ],
    'service_client' => [
      'label' => 'Service / Client',
      'addresses' => [
        'from' => 'passport_service',
        'to'   => 'entry_address',
      ]
    ],
];