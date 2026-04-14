<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\setting\Setting;
use finance\accounting\FiscalYear;
use identity\Organisation;
use realestate\property\Condominium;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;


[$params, $providers] = eQual::announce([
    'description'   => 'Generate a HTML rendering of the given balance sheet ("Bilan").',
    'params'        => [
        'params' => [
            'description'       => 'Optional params for rendering the targeted balance sheet.',
            'help'              => 'Expected/possible keys are: condo_id, account_id, fiscal_year_id, date_from, date_to.',
            'type'              => 'array',
            'required'          => true
        ],

        'domain' => [
            'description'   => 'Criterias that results have to match (series of conjunctions)',
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

$getFormattedDate = function($timestamp) {
    $tz = new DateTimeZone(constant('L10N_TIMEZONE'));
    $tz_offset = $tz->getOffset(new DateTime('@' . $timestamp));
    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    return date($date_format, $timestamp + $tz_offset);
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

$getLabels = function ($lang, $view_i18n_file_path, $default_labels = []) {
    $readLabels = function($path) {
        if(!$path || !file_exists($path)) {
            return [];
        }
        $labels = json_decode(file_get_contents($path), true);
        return is_array($labels) ? $labels : [];
    };

    return array_merge(
        $default_labels,
        $readLabels(sprintf('%s/packages/realestate/i18n/%s/_parts/header.json', EQ_BASEDIR, $lang)),
        $readLabels(sprintf('%s/packages/realestate/i18n/%s/_parts/footer.json', EQ_BASEDIR, $lang)),
        $readLabels($view_i18n_file_path)
    );
};

if(!isset($params['params']['condo_id'])) {
    throw new \Exception('missing_mandatory_condo_id', EQ_ERROR_MISSING_PARAM);
}

$condo_id = $params['params']['condo_id'];

$condominium = Condominium::id($condo_id)
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

$params = $params['params'];

$data = eQual::run('get', 'finance_accounting_balanceSheet_collect', [
        'domain'            => $params['domain'] ?? [],
        'date_from'         => ($params['date_from'] ?? null) ? strtotime($params['date_from']) : null,
        'date_to'           => ($params['date_to'] ?? null) ? strtotime($params['date_to']) : null,
        'condo_id'          => $params['condo_id'],
        'journal_id'        => $params['journal_id'] ?? null,
        'fiscal_year_id'    => $params['fiscal_year_id'] ?? null,
        'account_id'        => $params['account_id'] ?? null,
    ]);

// Resolve date interval
$date_from = null;
$date_to   = null;


if(!empty($params['fiscal_year_id'])) {
    $fiscalYear = FiscalYear::id($params['fiscal_year_id'])
        ->read(['date_from', 'date_to'])
        ->first();

    if($fiscalYear) {
        $date_from = $fiscalYear['date_from'];
        $date_to   = $fiscalYear['date_to'];
    }
}

if(!empty($params['date_from']) && (!$date_from || $params['date_from'] > $date_from)) {
    $date_from = $params['date_from'];
}

if(!empty($params['date_to']) && (!$date_to || $params['date_to'] < $date_to)) {
    $date_to = $params['date_to'];
}

$total_asset     = 0.0;
$total_liability = 0.0;
$lines           = [];

foreach($data as $line) {

    $asset_balance     = (float) ($line['asset_account_balance'] ?? 0);
    $liability_balance = (float) ($line['liability_account_balance'] ?? 0);

    $total_asset     += $asset_balance;
    $total_liability += $liability_balance;

    $line = [
        'asset' => [
            'code'        => $line['asset_account_code'] ?? null,
            'description' => $line['asset_account_description'] ?? null,
            'balance'     => $asset_balance
        ],
        'liability' => [
            'code'        => $line['liability_account_code'] ?? null,
            'description' => $line['liability_account_description'] ?? null,
            'balance'     => $liability_balance
        ]
    ];

    if(is_null($line['asset']['code'])) {
        $line['asset']['balance'] = null;
    }

    if(is_null($line['liability']['code'])) {
        $line['liability']['balance'] = null;
    }

    $lines[] = $line;
}

$subject = 'Bilan comptable';

$template = Template::search([
        ['code', '=', 'balance_sheet'],
        ['type', '=', 'document']
    ])
    ->read(['parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'condo'     => $condominium['name'],
            'date_to'   => $getFormattedDate($date_to),
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);
    }
}

$labels = $getLabels(
    $params['lang'],
    sprintf('%s/packages/finance/i18n/%s/accounting/%s.json', EQ_BASEDIR, $params['lang'], 'balanceSheet.'.$params['view_id']),
    [
        'balance_sheet.table.th.assets'                    => 'ASSETS',
        'balance_sheet.table.th.liabilities'               => 'LIABILITIES',
        'balance_sheet.table.footer.td.total_assets'       => 'Total assets',
        'balance_sheet.table.footer.td.total_liabilities'  => 'Total liabilities'
    ]
);

$values = [
    'title'               => $subject,

    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),
    'condominium'         => $condominium,

    'lines'               => $lines,
    'total_asset'         => $total_asset,
    'total_liability'     => $total_liability,

    // 'date'                => time(),
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

    $template = $twig->load('balanceSheet.'.$params['view_id'].'.html');
    $html = $template->render($values);

}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->body($html)
        ->send();
