<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use fmt\setting\Setting;
use finance\accounting\FiscalYear;
use identity\Organisation;
use realestate\funding\FundRequest;
use realestate\ownership\Owner;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;


list($params, $providers) = eQual::announce([
    'description'   => 'Generate an html view of given fund request for a single ownership.',
    'params'        => [
        'fiscal_year_id' => [
            'description'       => 'Identifier of the targeted Fiscal Year.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
            'required'          => true
        ],

        'ownership_id' => [
            'description'       => 'Identifier of the targeted Ownership (from which Owner is deduced).',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],
        'fund_request_id' => [
            'description'       => 'Identifier of the specific fund request that must be returned.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequest'
        ],
        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ],

        'view_id' => [
            'description' => 'View id of the template to use.',
            'type'        => 'string',
            'default'     => 'print.default'
        ],

        'lang' =>  [
            'description' => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'        => 'string',
            'default'     => constant('DEFAULT_LANG')
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'text/html',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];


$getFormattedDate = function($timestamp) {
    if(empty($timestamp) || !is_numeric($timestamp)) {
        return '';
    }
    try {
        $tz = new DateTimeZone(constant('L10N_TIMEZONE'));
        $tz_offset = $tz->getOffset(new DateTime('@' . $timestamp));
        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
        return date($date_format, $timestamp + $tz_offset);
    }
    catch(\Throwable $e) {
        return '';
    }
};

$getTwigCurrency = function($equal_currency) {
    $equal_twig_currency_map = [
        '€'   => 'EUR',
        '£'   => 'GBP',
        'CHF' => 'CHF',
        '$'   => 'USD'
    ];

    return $equal_twig_currency_map[$equal_currency] ?? $equal_currency;
};

$getOrganisationLogo = function($organisation_id, $object_class='identity\Organisation') {
    $result = '';

    $organisation = $object_class::id($organisation_id)->read(['profile_image_print'])->first();

    if($organisation && $organisation['profile_image_print']) {
        $result = sprintf('data:%s;base64,%s',
            'image/jpeg',
            base64_encode($organisation['profile_image_print'])
        );
    }
    return $result;
};

$getPaymentQrCodeUri = function($legal_name, $bank_account_iban, $bank_account_bic, $payment_reference, $amount) {
    // default to blank image (empty 1x1)
    $result = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    try {
        $image = eQual::run('get', 'finance_payment_generate-qr-code', [
                'recipient_name'    => $legal_name,
                'recipient_iban'    => $bank_account_iban,
                'recipient_bic'     => $bank_account_bic,
                'payment_reference' => $payment_reference,
                'payment_amount'    => $amount
            ]);
        $result = sprintf('data:%s;base64,%s',
                'image/png',
                base64_encode($image)
            );
    }
    catch(Exception $e) {
        trigger_error("APP::unable to generate QR code for $bank_account_iban: " . $e->getMessage(), EQ_REPORT_WARNING);
    }
    return $result;
};

$getLabels = function ($lang, $view_i18n_file_path) {
    $header_labels_json = file_get_contents(
        sprintf('%s/packages/realestate/i18n/%s/_parts/header.json', EQ_BASEDIR, $lang)
    );
    $header_labels = json_decode($header_labels_json, true);

    $footer_labels_json = file_get_contents(
        sprintf('%s/packages/realestate/i18n/%s/_parts/footer.json', EQ_BASEDIR, $lang)
    );
    $footer_labels = json_decode($footer_labels_json, true);

    $labels_json = file_get_contents($view_i18n_file_path);
    $labels = json_decode($labels_json, true);

    return array_merge(
        $header_labels,
        $footer_labels,
        $labels
    );
};


$fund_requests = [];
$executions = [];


// load all fund requests from a given fiscal year
$fiscalYear = FiscalYear::id($params['fiscal_year_id'])
    ->read([
        'date_from',
        'date_to',
        'fund_requests_ids',
        'condo_id' => [
            'name', 'address_street', 'address_zip', 'address_city',
            'registration_number',
            'managing_agent_id' => [
                'name', 'address_street', 'address_dispatch', 'address_zip',
                'address_city', 'address_country', 'has_vat', 'vat_number',
                'legal_name', 'registration_number', 'bank_account_iban', 'bank_account_bic',
                'website', 'email', 'phone', 'has_vat', 'vat_number',
                'profile_image_document_id' => [
                    'type', 'data'
                ]
            ]
        ]
    ])
    ->first(true);

if(!$fiscalYear) {
    throw new Exception('unknown_fiscal_year', EQ_ERROR_INVALID_PARAM);
}

$organisation = Organisation::id(1)
    ->read([
        'name', 'address_street', 'address_dispatch', 'address_zip',
        'address_city', 'address_country', 'has_vat', 'vat_number',
        'legal_name', 'registration_number', 'bank_account_iban', 'bank_account_bic',
        'website', 'email', 'phone', 'has_vat', 'vat_number',
        'profile_image_document_id' => [
            'type', 'data'
        ]
    ])
    ->first();

// take all requests of the fiscal year under account
$fund_requests_ids = $fiscalYear['fund_requests_ids'];
// unless a specific one is provided
if(isset($params['fund_request_id'])) {
    $fund_requests_ids = [$params['fund_request_id']];
}

foreach($fund_requests_ids as $fund_request_id) {
    $fundRequest = FundRequest::id($fund_request_id)
        ->read([
            'status',
            'name',
            'request_date',
            'has_date_range',
            'date_range_frequency',
            'fiscal_period_id' => ['name'],
            'date_from',
            'date_to',
            'condo_id' => ['name'],
            'entry_lots_ids' => [
                '@domain' => ['ownership_id', '=', $params['ownership_id']],
                'ownership_id',
                'apportionment_shares',
                'allocated_amount',
                'property_lot_id'   => ['name', 'code', 'property_lot_ref'],
                'request_line_id'   => ['apportionment_id' => ['name', 'total_shares'], 'request_amount'],
                'line_entry_id'     => ['allocated_amount']
            ],
            'execution_lines_ids' => [
                '@domain' => ['ownership_id', '=', $params['ownership_id']],
                'ownership_id',
                'price',
                'invoice_id' => ['emission_date', 'due_date', 'status']
            ]
        ])
        ->first(true);


    if($fundRequest['status'] != 'active') {
        continue;
    }

    $request_name = $fundRequest['name'];

    if(!$fundRequest['has_date_range']) {
        $request_name .= ' (' . $getFormattedDate($fundRequest['request_date']) . ')';
    }
    else {
        $request_name .= ' (' . $getFormattedDate($fundRequest['date_from']) . ' - ' . $getFormattedDate($fundRequest['date_to']) . ')';
    }

    $fund_request = [
            'name'          => $request_name,
            'lines'         => [],
            'executions'    => [],
            'total'         => 0.0
        ];

    foreach($fundRequest['entry_lots_ids'] as $entry_lot) {
        $line = [
            'name'          => $entry_lot['property_lot_id']['name'],
            'code'          => $entry_lot['property_lot_id']['code'],
            'apportionment' => $entry_lot['request_line_id']['apportionment_id']['name'],
            'total'         => $entry_lot['request_line_id']['request_amount'],
            'shares'        => $entry_lot['apportionment_shares'] . '/' . $entry_lot['request_line_id']['apportionment_id']['total_shares'],
            'amount'        => $entry_lot['allocated_amount']
        ];
        $fund_request['total'] += $entry_lot['allocated_amount'];
        $fund_request['lines'][] = $line;
    }

    foreach($fundRequest['execution_lines_ids'] as $execution_line) {
        if($execution_line['invoice_id']['status'] === 'cancelled') {
            continue;
        }
        $line = [
            'issue_date'    => $execution_line['invoice_id']['emission_date'],
            'due_date'      => $execution_line['invoice_id']['due_date'],
            'fund_request'  => $request_name,
            'amount'        => $execution_line['price']
        ];
        $executions[] = $line;

    }
    $fund_requests[] = $fund_request;
}

usort($executions, function ($a, $b) {
    return $a['date'] <=> $b['date'];
});

/*
    #todo
    copropriétaire

        déterminer le bloc adresse en fonction du ownership_type et has_representant
        pour le moment, on prend l'identity du premier owner associé au ownership_id

    si has_representative use representative_identity_id
    sinon soit on prendre le premier owner de la liste, soit on prend le owner_id renseigné dans les params (pour courrier personnalisé, même s'il s'agit du même ownership)
*/

$owner = Owner::search(['ownership_id', '=', $params['ownership_id']])
    ->read([
        'identity_id' => [
            'name', 'address_street', 'address_dispatch', 'address_zip',
            'address_city', 'address_country', 'has_vat', 'vat_number',
            'lang_id' => ['code']
        ]
    ])
    ->first();

if(!$owner) {
    throw new Exception('unknown_owner', EQ_ERROR_INVALID_PARAM);
}

$lang = $owner['identity_id']['lang_id']['code'];

// retrieve template (subject & body)
$subject = 'Appels de fonds';
$introduction = '';

$template = Template::search([
        ['code', '=', 'fund_request_execution_correspondence'],
        ['type', '=', 'document']
    ])
    ->read(['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'condo'             => $fundRequest['condo_id']['name'],
            'period'            => $fundRequest['fiscal_period_id']['name']
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] == 'introduction') {
        $introduction = $part['value'];

        $map_values = [
            'firstname'         => $owner['identity_id']['firstname'],
            'lastname'          => $owner['identity_id']['lastname'],
            'condo'             => $fundRequest['condo_id']['name'],
            'period'            => $fundRequest['fiscal_period_id']['name']
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
}

// adapt specific properties to TXT output
/*
$invoice['payment_reference'] = DataFormatter::format($invoice['payment_reference'], 'scor');
$invoice['organisation_id']['bank_account_iban'] = DataFormatter::format($invoice['organisation_id']['bank_account_iban'], 'iban');
$invoice['organisation_id']['phone'] = DataFormatter::format($invoice['organisation_id']['phone'], 'phone');
*/

$labels = $getLabels($lang, sprintf('%s/packages/realestate/i18n/%s/funding/%s.json', EQ_BASEDIR, $lang, 'FundRequest.'.$params['view_id']));

$values = [
    'title'               => $subject,
    'introduction'        => $introduction,

    'fiscal_period'       => [
            'date_from'     => $fiscalYear['date_from'],
            'date_to'       => $fiscalYear['date_to'],
        ],
    'fund_requests'       => $fund_requests,
    'executions'          => $executions,

    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),

    'condominium'         => $fiscalYear['condo_id'],

    'recipient'           => $owner['identity_id'],

//    'payment_qr_code_uri' => $getPaymentQrCodeUri($invoice),
    'date'                => time(),
    'timezone'            => constant('L10N_TIMEZONE'),
    'locale'              => constant('L10N_LOCALE'),
    'date_format'         => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),
    'currency'            => $getTwigCurrency(Setting::get_value('core', 'locale', 'currency', '€')),
    'labels'              => $labels,
    'debug'               => $params['debug']
];



try {
    // generate HTML
    $loader = new TwigFilesystemLoader([
            EQ_BASEDIR.'/packages/realestate/views/_parts',
            EQ_BASEDIR.'/packages/realestate/views/funding'
        ]);

    $twig = new TwigEnvironment($loader);

    /** @var ExtensionInterface $extension **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    // #todo - temp workaround against LOCALE mixups
    $twig->addFilter(
            new TwigFilter('format_money', function ($value, $currency=true) {
                if(is_null($value)) {
                    return '';
                }
                if($currency) {
                    return number_format((float) $value, 2, ",", ".") . ' €';
                }
                return number_format((float) $value, 2, ",", ".");
            })
        );

    $template = $twig->load('FundRequest.'.$params['view_id'].'.html');
    $html = $template->render($values);

}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->body($html)
        ->send();
