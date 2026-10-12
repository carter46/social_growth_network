<?php

/*
| Payout destinations agents can add. A country/method pair is only offered when payout
| coverage marks at least one gateway verified for it (see PayoutCoverageService).
|
| Each method points at a schema, which lists the fields the form shows and validates.
| name_check: "auto" means a gateway lookup must return the holder name; anything else
| saves the account as pending_review after the agent confirms the name.
*/

$sepa = ['AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK'];

$sepaNames = [
    'AT' => 'Austria', 'BE' => 'Belgium', 'CY' => 'Cyprus', 'DE' => 'Germany', 'EE' => 'Estonia',
    'ES' => 'Spain', 'FI' => 'Finland', 'FR' => 'France', 'GR' => 'Greece', 'HR' => 'Croatia',
    'IE' => 'Ireland', 'IT' => 'Italy', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia',
    'MT' => 'Malta', 'NL' => 'Netherlands', 'PT' => 'Portugal', 'SI' => 'Slovenia', 'SK' => 'Slovakia',
];

$countries = [
    'NG' => ['name' => 'Nigeria', 'currency' => 'NGN', 'methods' => [
        'bank_account' => ['schema' => 'local_bank', 'name_check' => 'auto'],
    ]],
    'GH' => ['name' => 'Ghana', 'currency' => 'GHS', 'methods' => [
        'bank_account' => ['schema' => 'local_bank', 'name_check' => 'auto'],
        'mobile_money' => ['schema' => 'mobile_money', 'name_check' => 'auto'],
    ]],
    'KE' => ['name' => 'Kenya', 'currency' => 'KES', 'methods' => [
        'bank_account' => ['schema' => 'local_bank', 'name_check' => 'confirm'],
        'mobile_money' => ['schema' => 'mobile_money', 'name_check' => 'confirm'],
    ]],
    'UG' => ['name' => 'Uganda', 'currency' => 'UGX', 'methods' => [
        'bank_account' => ['schema' => 'local_bank', 'name_check' => 'confirm'],
        'mobile_money' => ['schema' => 'mobile_money', 'name_check' => 'confirm'],
    ]],
    'TZ' => ['name' => 'Tanzania', 'currency' => 'TZS', 'methods' => [
        'bank_account' => ['schema' => 'local_bank', 'name_check' => 'confirm'],
        'mobile_money' => ['schema' => 'mobile_money', 'name_check' => 'confirm'],
    ]],
    'RW' => ['name' => 'Rwanda', 'currency' => 'RWF', 'methods' => [
        'mobile_money' => ['schema' => 'mobile_money', 'name_check' => 'confirm'],
    ]],
    'ZA' => ['name' => 'South Africa', 'currency' => 'ZAR', 'methods' => [
        'bank_account' => ['schema' => 'local_bank', 'name_check' => 'confirm'],
    ]],
    'US' => ['name' => 'United States', 'currency' => 'USD', 'methods' => [
        'bank_account' => ['schema' => 'us_bank', 'name_check' => 'confirm'],
    ]],
    'GB' => ['name' => 'United Kingdom', 'currency' => 'GBP', 'methods' => [
        'bank_account' => ['schema' => 'gb_bank', 'name_check' => 'confirm'],
    ]],
    'CA' => ['name' => 'Canada', 'currency' => 'CAD', 'methods' => [
        'bank_account' => ['schema' => 'ca_bank', 'name_check' => 'confirm'],
    ]],
];

foreach ($sepa as $code) {
    $countries[$code] = ['name' => $sepaNames[$code], 'currency' => 'EUR', 'methods' => [
        'bank_account' => ['schema' => 'sepa', 'name_check' => 'confirm'],
    ]];
}

return [

    'methods' => [
        'bank_account' => 'Bank account',
        'mobile_money' => 'Mobile money',
    ],

    /*
    | Field types: text, digits, select (options from the gateway bank or network list),
    | choice (fixed options). "mask" marks the field used for the masked display.
    */
    'schemas' => [
        'local_bank' => [
            'fields' => [
                'bank_code' => ['label' => 'Bank', 'type' => 'select', 'source' => 'banks', 'required' => true],
                'account_number' => ['label' => 'Account number', 'type' => 'digits', 'min' => 6, 'max' => 20, 'required' => true, 'mask' => true],
            ],
        ],
        'mobile_money' => [
            'fields' => [
                'mobile_provider' => ['label' => 'Network', 'type' => 'select', 'source' => 'networks', 'required' => true],
                'phone_number' => ['label' => 'Wallet phone number (with country code)', 'type' => 'digits', 'min' => 9, 'max' => 15, 'required' => true, 'mask' => true],
            ],
        ],
        'us_bank' => [
            'fields' => [
                'scheme' => ['label' => 'Transfer type', 'type' => 'choice', 'options' => ['ach' => 'ACH', 'wire' => 'Wire', 'rtp' => 'RTP'], 'required' => true],
                'bank_name' => ['label' => 'Bank name', 'type' => 'text', 'max' => 120, 'required' => true],
                'routing_number' => ['label' => 'Routing number (9 digits)', 'type' => 'digits', 'min' => 9, 'max' => 9, 'required' => true, 'validator' => 'aba'],
                'account_number' => ['label' => 'Account number', 'type' => 'digits', 'min' => 4, 'max' => 17, 'required' => true, 'mask' => true],
                'bank_address_line1' => ['label' => 'Bank branch street address', 'type' => 'text', 'max' => 160, 'required' => true],
                'bank_address_city' => ['label' => 'Bank city', 'type' => 'text', 'max' => 80, 'required' => true],
                'bank_address_state' => ['label' => 'Bank state (2 letters)', 'type' => 'text', 'max' => 2, 'required' => true],
                'bank_address_postal_code' => ['label' => 'Bank ZIP code', 'type' => 'text', 'max' => 10, 'required' => true],
            ],
        ],
        'gb_bank' => [
            'fields' => [
                'bank_name' => ['label' => 'Bank name', 'type' => 'text', 'max' => 120, 'required' => true],
                'sort_code' => ['label' => 'Sort code (6 digits)', 'type' => 'digits', 'min' => 6, 'max' => 6, 'required' => true, 'validator' => 'sort_code'],
                'account_number' => ['label' => 'Account number (8 digits)', 'type' => 'digits', 'min' => 8, 'max' => 8, 'required' => true, 'mask' => true],
            ],
        ],
        'sepa' => [
            'fields' => [
                'bank_name' => ['label' => 'Bank name', 'type' => 'text', 'max' => 120, 'required' => true],
                'iban' => ['label' => 'IBAN', 'type' => 'text', 'max' => 34, 'required' => true, 'validator' => 'iban', 'mask' => true],
                'swift_bic' => ['label' => 'BIC', 'type' => 'text', 'max' => 11, 'required' => true, 'validator' => 'swift'],
            ],
        ],
        'ca_bank' => [
            'fields' => [
                'scheme' => ['label' => 'Transfer type', 'type' => 'choice', 'options' => ['eft' => 'EFT', 'interac' => 'Interac to bank account'], 'required' => true],
                'bank_name' => ['label' => 'Bank name', 'type' => 'text', 'max' => 120, 'required' => true],
                'institution_number' => ['label' => 'Institution number (3 digits)', 'type' => 'digits', 'min' => 3, 'max' => 3, 'required' => true, 'validator' => 'ca_institution'],
                'transit_number' => ['label' => 'Transit number (5 digits)', 'type' => 'digits', 'min' => 5, 'max' => 5, 'required' => true, 'validator' => 'ca_transit'],
                'account_number' => ['label' => 'Account number', 'type' => 'digits', 'min' => 5, 'max' => 12, 'required' => true, 'mask' => true],
            ],
        ],
    ],

    'countries' => $countries,

];
