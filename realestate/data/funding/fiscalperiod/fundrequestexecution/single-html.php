<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use equal\data\DataFormatter;
use fmt\setting\Setting;
use identity\Organisation;
use realestate\funding\FundRequest;
use realestate\funding\FundRequestExecution;
use realestate\ownership\Owner;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;


[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of given fund request for a single ownership.',
    'params'        => [
        'fund_request_execution_id' => [
            'description'       => 'Identifier of the specific ExpenseStatementCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequestExecution',
            'required'          => true
        ],

        'ownership_id' => [
            'description'       => 'Identifier of the targeted Ownership (from which Owner is deduced).',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],

        'owner_id' => [
            'description'       => 'Identifier of the targeted Owner, if any.',
            'help'              => 'If not provided, fallback to first Owner of given Ownership.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Owner',
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


$dataFormatter = function ($value, $usage) {
    if(is_null($value)) {
        return '';
    }
    return DataFormatter::format($value, $usage);
};

/**
 * @param string|float|integer $value
 * @param bool $currency
 * @return string
 */
$formatMoney = function ($value, $currency=true) {
    if(is_null($value)) {
        return '';
    }
    if($currency) {
        return number_format((float) $value, 2, ",", ".") . ' €';
    }
    return number_format((float) $value, 2, ",", ".");
};

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

/** @var \realestate\funding\FundRequestExecution $fundRequestExecution */
$fundRequestExecution = FundRequestExecution::id($params['fund_request_execution_id'])
    ->read([
        'fund_request_id',
        'date_from',
        'date_to',
        'posting_date',
        'due_date',
        'price',
        'status',
        // #memo - there should be only one funding matching the ownership
        'fundings_ids' => [
            '@domain' => [['ownership_id', '=', $params['ownership_id']], ['funding_type', '=', 'fund_request']],
            'payment_reference',
            'remaining_amount',
            'due_date'
        ],
        // #memo - there should be only one execution line matching the ownership
        'execution_lines_ids' => [
            '@domain' => ['ownership_id', '=', $params['ownership_id']],
            'price'
        ],
        'condo_id' => [
            'name', 'legal_name', 'address_street', 'address_zip', 'address_city',
            'registration_number', 'bank_account_iban', 'bank_account_bic',
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
    ->first();

/*
// #memo - on ne peut pas donner le détail du calcul quotités par exécution : il n'existe pas puisque le découpage est fait a posteriori, sur un nombre arbitraire de périodes, et avec des éventuelles gestion d'arrondis
*/
if(!$fundRequestExecution) {
    throw new Exception('unknown_fund_request_execution', EQ_ERROR_INVALID_PARAM);
}

if($fundRequestExecution['status'] === 'cancelled') {
    throw new Exception('cancelled_fund_request_execution', EQ_ERROR_INVALID_PARAM);
}

/** @var \realestate\funding\FundRequest $fundRequest */
$fundRequest = FundRequest::id($fundRequestExecution['fund_request_id'])
    ->read([
        'status',
        'name',
        'request_date',
        'request_amount',
        'request_type',
        'has_date_range',
        'date_range_frequency',
        'date_from',
        'date_to',
        'request_bank_account_id' => ['bank_account_iban', 'bank_account_bic'],
        'fiscal_period_id' => [
            'name',
            'date_from',
            'date_to'
        ],
        'request_lines_ids' => ['name', 'request_amount', 'apportionment_id' => ['name', 'total_shares']],
        'line_entries_ids'  => [
            '@domain' => ['ownership_id', '=', $params['ownership_id']],
            'apportionment_shares',
            'allocated_amount',
            'request_line_id'
        ],
        'request_executions_ids' => [
            'posting_date',
            'due_date',
            'price',
            'execution_lines_ids' => [
                '@domain' => ['ownership_id', '=', $params['ownership_id']],
                'price'
            ]
        ]
    ])
    ->first();

if($fundRequest['status'] === 'cancelled') {
    throw new Exception('cancelled_fund_request', EQ_ERROR_INVALID_PARAM);
}

$execution = $fundRequestExecution->toArray();

$funding = $fundRequestExecution['fundings_ids']->first(true);

$executions = $fundRequest['request_executions_ids']->get(true);

$fund_request = [
        'name'          => $fundRequest['name'],
        'lines'         => [],
        'owner_total'   => 0.0
    ];

$map_request_line_entries = [];

foreach($fundRequest['line_entries_ids'] as $request_line_entry_id => $requestLineEntry) {
    $map_request_line_entries[$requestLineEntry['request_line_id']] = $requestLineEntry;
}

foreach($fundRequest['request_lines_ids'] as $request_line_id => $requestLine) {
    $fund_request['lines'][] = [
        'apportionment'     => $requestLine['apportionment_id']['name'],
        'total_shares'      => $requestLine['apportionment_id']['total_shares'],
        'request_amount'    => $requestLine['request_amount'],
        'owner_shares'      => $map_request_line_entries[$request_line_id]['apportionment_shares'] ?? 0,
        'owner_total'       => $map_request_line_entries[$request_line_id]['allocated_amount'] ?? 0
    ];

    $fund_request['owner_total'] += $map_request_line_entries[$request_line_id]['allocated_amount'];
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


// retrieve Owner : required for Correspondence but optional for preview (fallback to first owner of given ownership)
if($params['owner_id']) {
    $ownerCollection = Owner::id($params['owner_id']);
}
elseif($params['ownership_id']) {
    $ownerCollection = Owner::search(['ownership_id', '=', $params['ownership_id']]);
}
else {
    throw new Exception('missing_ownership', EQ_ERROR_INVALID_PARAM);
}

$owner = $ownerCollection->read([
        'ownership_id' => ['address_recipient'],
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
$communication = '';

$template = Template::search([
        ['code', '=', 'fund_request_execution_correspondence'],
        ['type', '=', 'document']
    ])
    ->read(['id','parts_ids' => ['name', 'value']])
    ->first(true);

if(!$template) {
    throw new Exception('template_not_found', EQ_ERROR_INVALID_CONFIG);
}

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'condo'             => $fundRequestExecution['condo_id']['name'],
            'period'            => $fundRequest['fiscal_period_id']['name'],
            'date_from'         => $getFormattedDate($fundRequestExecution['date_from']),
            'date_to'           => $getFormattedDate($fundRequestExecution['date_to']),
            'label'             => $fundRequest['name']
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
            'condo'             => $fundRequestExecution['condo_id']['name'],
            'period'            => $fundRequest['fiscal_period_id']['name'],
            'date_from'         => $getFormattedDate($fundRequestExecution['date_from']),
            'date_to'           => $getFormattedDate($fundRequestExecution['date_to'])
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
    elseif($part['name'] == 'communication_payment_amount' && $funding && $funding['remaining_amount'] >= 0.01) {
        $communication = $part['value'];

        $map_values = [
            'remaining_amount'  => $formatMoney($funding['remaining_amount']),
            'due_date'          => $getFormattedDate($funding['due_date'] ?? $fundRequestExecution['due_date'] ?? time())
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $communication = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $communication);
    }
    elseif($part['name'] == 'communication_reimbursement' && $funding && $funding['remaining_amount'] < 0.0) {
        $communication = $part['value'];

        $map_values = [
            'remaining_amount' => $formatMoney(abs($funding['remaining_amount']))
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $communication = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $communication);
    }
    elseif($part['name'] == 'communication_no_action_required' && $funding && abs($funding['remaining_amount']) < 0.01) {
        $communication = $part['value'];
    }
}

$labels = $getLabels($lang, sprintf('%s/packages/realestate/i18n/%s/funding/%s.json', EQ_BASEDIR, $lang, 'FundRequestExecution.' . $params['view_id']));

$values = [
    'title'               => $subject,
    'introduction'        => $introduction,
    'communication'       => $communication,

    'fund_request'        => $fund_request,
    'execution'           => $execution,
    'executions'          => $executions,

    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),

    'condominium'         => $fundRequestExecution['condo_id'],

    'recipient'           => $owner['identity_id'],

    'funding'             => $funding,
    'payment_qr_code_uri' => $getPaymentQrCodeUri(
                $fundRequestExecution['condo_id']['legal_name'],
                $fundRequest['request_bank_account_id']['bank_account_iban'] ?? $fundRequestExecution['condo_id']['bank_account_iban'],
                $fundRequest['request_bank_account_id']['bank_account_bic'] ?? $fundRequestExecution['condo_id']['bank_account_bic'],
                $funding['payment_reference'] ?? '',
                $funding['remaining_amount'] ?? 0
            ),

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
            new TwigFilter('format_money', $formatMoney)
        );

    $twig->addFilter(
            new TwigFilter('data_format', $dataFormatter)
        );

    $template = $twig->load('FundRequestExecution.' . $params['view_id'] . '.html');
    $html = $template->render($values);

}
catch(\Throwable $e) {
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('rendering_error', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
        ->body($html)
        ->send();
