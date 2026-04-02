<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\setting\Setting;
use identity\Organisation;
use realestate\property\Condominium;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;


[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF file of the given general ledger.',
    'params'        => [
        'params' => [
            'description'       => 'Optional identifier of a specific targeted Ownership to limit to rendering to.',
            'help'              => 'Expected/possible keys are: condo_id, account_id, fiscal_year_id, date_from, date_to.',
            'type'              => 'array',
            'required'          => true
        ],

        'domain' => [
            'description'   => 'Criteria that results have to match (series of conjunctions)',
            'type'          => 'array',
            'default'       => []
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
            'default'     => 'fr'
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


if(!isset($params['params']['condo_id'])) {
    throw new \Exception('missing_mandatory_condo_id', EQ_ERROR_MISSING_PARAM);
}

$condominium = Condominium::id($params['params']['condo_id'])
    ->read([
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
    ])
    ->first();

if(!$condominium) {
    throw new \Exception('unknown_condominium', EQ_ERROR_INVALID_PARAM);
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

$data = eQual::run('get', 'finance_accounting_generalLedger_collect', [
        'domain'            => $params['domain'] ?? [],
        'date_from'         => ($params['params']['date_from']) ? strtotime($params['params']['date_from']) : null,
        'date_to'           => ($params['params']['date_to']) ? strtotime($params['params']['date_to']) : null,
        'condo_id'          => $params['params']['condo_id'],
        'journal_id'        => $params['params']['journal_id'] ?? null,
        'fiscal_year_id'    => $params['params']['fiscal_year_id'] ?? null,
        'account_id'        => $params['params']['account_id'] ?? null,
    ]);


$groups = [];

// 1) Group by account_id
foreach($data as $line) {

    $account_id    = $line['account_id']['id'];
    $account_name  = $line['account_id']['name']; // "7420005 - Loyers ..."

    // Extract account code + label (split at first " - ")
    $parts = explode(' - ', $account_name, 2);
    $account_code  = $parts[0];
    $account_label = $parts[1] ?? '';

    if(!isset($groups[$account_id])) {
        $groups[$account_id] = [
            'account_id'    => $account_id,
            'account_code'  => $account_code,
            'account_label' => $account_label,
            'entries'       => [],
            'totals' => [
                'debit'  => 0,
                'credit' => 0,
                'balance'=> 0
            ]
        ];
    }

    $groups[$account_id]['entries'][] = [
        'entry_date'        => $line['entry_date'],
        'entry_journal'     => $line['entry_journal'],
        'entry_number'      => $line['entry_number'],
        'entry_reference'   => $line['entry_reference'],
        'description'       => $line['description'],
        'debit'             => floatval($line['debit']),
        'credit'            => floatval($line['credit']),
        'balance'           => floatval($line['balance'])
    ];
}


// 2) Sort entries per account (by account_code then account_label)
usort($groups, function ($a, $b) {
    // `code` guarantees correct ordering (from accounting account numbering)
    $cmp = strcmp($a['account_code'], $b['account_code']);
    if($cmp !== 0) {
        return $cmp;
    }

    // fallback to account_label if code are the same (shouldn't occur)
    return strcmp($a['account_label'], $b['account_label']);
});

$grand_totals = [
    'debit'  => 0,
    'credit' => 0,
    'balance'=> 0
];

// 3) Compute running balance + totals
foreach($groups as $account_id => &$group) {
    $runningBalance = 0;
    $totalDebit  = 0;
    $totalCredit = 0;

    foreach($group['entries'] as &$entry) {

        $totalDebit  += $entry['debit'];
        $totalCredit += $entry['credit'];

        // Increase balance: debit = +, credit = -
        $runningBalance += ($entry['debit'] - $entry['credit']);

        $entry['balance_after'] = $runningBalance;
    }

    $group['totals']['debit']  = $totalDebit;
    $group['totals']['credit'] = $totalCredit;
    $group['totals']['balance'] = $runningBalance;

    $grand_totals['debit']  += $totalDebit;
    $grand_totals['credit'] += $totalCredit;
    $grand_totals['balance'] += $runningBalance;
}

$subject = 'Grand Livre';

$template = Template::search([
        ['code', '=', 'general_ledger'],
        ['type', '=', 'document']
    ])
    ->read(['parts_ids' => ['name', 'value']])
    ->first();

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);
    }
}

$labels = $getLabels($params['lang'], sprintf('%s/packages/finance/i18n/%s/accounting/%s.json', EQ_BASEDIR, $params['lang'], 'generalLedger.'.$params['view_id']));

$values = [
    'title'               => $subject,

    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),
    'condominium'         => $condominium,

    'accounts'            => array_values($groups),
    'grand_totals'        => $grand_totals,

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
    $loader = new TwigFilesystemLoader ([
            EQ_BASEDIR.'/packages/realestate/views/_parts',
            EQ_BASEDIR.'/packages/finance/views/accounting'
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

    $template = $twig->load('generalLedger.'.$params['view_id'].'.html');
    $html = $template->render($values);

}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->body($html)
        ->send();
