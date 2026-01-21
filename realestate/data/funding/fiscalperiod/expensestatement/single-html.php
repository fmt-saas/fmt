<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\setting\Setting;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;
use finance\accounting\FiscalPeriod;
use identity\Organisation;
use realestate\funding\ExpenseStatement;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of given fund request.',
    'help' => 'This action is a variation of the expense statement generation, producing a preview PDF for a single ownership within a specified fiscal period.',
    'params'        => [

        'fiscal_period_id' => [
            'label'             => 'Fiscal Period',
            'description'       => 'Identifier of the targeted Fiscal Period.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalPeriod',
            'required'          => true
        ],

        'ownership_id' => [
            'description'       => 'Identifier of the targeted Ownership (from which Owner is deduced).',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
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

$buildOwnerExpenses = function (array $owner): array {

    $expenses = [];

    foreach($owner['property_lots'] as $lot) {

        foreach($lot['expenses'] as $expense) {
            $expense_type = $expense['name'];

            if (!isset($expenses[$expense_type])) {
                $expenses[$expense_type] = [
                    'name'           => $expense['name'],
                    'apportionments' => []
                ];
            }

            foreach($expense['apportionments'] as $apportionment) {
                $apportionment_id = $apportionment['id'];

                if(!isset($expenses[$expense_type]['apportionments'][$apportionment_id])) {
                    $expenses[$expense_type]['apportionments'][$apportionment_id] = [
                        'id'            => $apportionment['id'],
                        'name'          => $apportionment['name'],
                        'total_shares'  => $apportionment['total_shares'],
                        'shares'        => $apportionment['shares'],
                        'accounts'      => [],
                        'total_amount'  => 0.0,
                        'total_vat'     => 0.0,
                        'total_owner'   => 0.0,
                        'total_tenant'  => 0.0
                    ];
                }
                else {
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['shares'] += $apportionment['shares'];
                }

                foreach($apportionment['accounts'] as $account) {
                    $account_code = $account['code'];

                    if(!isset(
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]
                    )) {
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]
                            = [
                                'id'            => $account['id'],
                                'name'          => $account['name'],
                                'code'          => $account['code'],
                                'total_amount'  => $account['total_amount'],
                                'owner'         => $account['owner'],
                                'tenant'        => $account['tenant'],
                                'vat'           => $account['vat']
                            ];
                    }
                    else {
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]['owner']
                            += $account['owner'];
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]['tenant']
                            += $account['tenant'];
                        $expenses[$expense_type]['apportionments'][$apportionment_id]['accounts'][$account_code]['vat']
                            += $account['vat'];
                    }
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_amount']   += $account['total_amount'];
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_vat']      += $account['vat'];
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_owner']    += $account['owner'];
                    $expenses[$expense_type]['apportionments'][$apportionment_id]['total_tenant']   += $account['tenant'];
                }
            }
        }
    }

    return $expenses;
};


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

$getLabels = function($lang) {
    return [
        'invoice'                        => Setting::get_value('sale', 'locale', 'label_invoice', 'Invoice', [], $lang),
        'credit_note'                    => Setting::get_value('sale', 'locale', 'label_credit-note', 'Credit note', [], $lang),
        'customer_name'                  => Setting::get_value('sale', 'locale', 'label_customer-name', 'Name', [], $lang),
        'customer_address'               => Setting::get_value('sale', 'locale', 'label_customer-address', 'Address', [], $lang),
        'registration_number'            => Setting::get_value('sale', 'locale', 'label_registration-number', 'Registration n°', [], $lang),
        'vat_number'                     => Setting::get_value('sale', 'locale', 'label_vat-number', 'VAT n°', [], $lang),
        'number'                         => Setting::get_value('sale', 'locale', 'label_number', 'N°', [], $lang),
        'date'                           => Setting::get_value('sale', 'locale', 'label_date', 'Date', [], $lang),
        'status'                         => Setting::get_value('sale', 'locale', 'label_status', 'Status', [], $lang),
        'status_paid'                    => Setting::get_value('sale', 'locale', 'label_status-paid', 'Paid', [], $lang),
        'status_to_pay'                  => Setting::get_value('sale', 'locale', 'label_status-to-pay', 'To pay', [], $lang),
        'status_to_refund'               => Setting::get_value('sale', 'locale', 'label_status-to-refund', 'To refund', [], $lang),
        'proforma_notice'                => Setting::get_value('sale', 'locale', 'label_proforma-notice', 'This is a proforma and must not be paid.', [], $lang),
        'total_excl_vat'                 => Setting::get_value('sale', 'locale', 'label_total-ex-vat', 'Total VAT excl.', [], $lang),
        'total_incl_vat'                 => Setting::get_value('sale', 'locale', 'label_total-inc-vat', 'Total VAT incl.', [], $lang),
        'balance_of_must_be_paid_before' => Setting::get_value('sale', 'locale', 'label_balance-of-must-be-paid-before', 'Balance of %price% to be paid before %due_date%', [], $lang),
        'communication'                  => Setting::get_value('sale', 'locale', 'label_communication', 'Communication', [], $lang),
        'columns' => [
            'product'                    => Setting::get_value('sale', 'locale', 'label_product-column', 'Product label', [], $lang),
            'qty'                        => Setting::get_value('sale', 'locale', 'label_qty-column', 'Qty', [], $lang),
            'free'                       => Setting::get_value('sale', 'locale', 'label_free-column', 'Free', [], $lang),
            'unit_price'                 => Setting::get_value('sale', 'locale', 'label_unit-price-column', 'U. price', [], $lang),
            'discount'                   => Setting::get_value('sale', 'locale', 'label_discount-column', 'Disc.', [], $lang),
            'vat'                        => Setting::get_value('sale', 'locale', 'label_vat-column', 'VAT', [], $lang),
            'taxes'                      => Setting::get_value('sale', 'locale', 'label_taxes-column', 'Taxes', [], $lang),
            'price_ex_vat'               => Setting::get_value('sale', 'locale', 'label_price-ex-vat-column', 'Price ex. VAT', [], $lang),
            'price'                      => Setting::get_value('sale', 'locale', 'label_price-column', 'Price', [], $lang)
        ],
        'footer' => [
            'registration_number'        => Setting::get_value('sale', 'locale', 'label_footer-registration-number', 'Registration number', [], $lang),
            'iban'                       => Setting::get_value('sale', 'locale', 'label_footer-iban', 'IBAN', [], $lang),
            'email'                      => Setting::get_value('sale', 'locale', 'label_footer-email', 'Email', [], $lang),
            'web'                        => Setting::get_value('sale', 'locale', 'label_footer-web', 'Web', [], $lang),
            'tel'                        => Setting::get_value('sale', 'locale', 'label_footer-tel', 'Tel', [], $lang)
        ]
    ];
};


$fiscalPeriod = FiscalPeriod::id($params['fiscal_period_id'])
    ->read([
        'date_from',
        'date_to',
        'name',
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
    ->first();

if(!$fiscalPeriod) {
    throw new Exception('unknown_fiscal_period', EQ_ERROR_UNKNOWN_OBJECT);
}

$statement = ExpenseStatement::search([
        ['fiscal_period_id', '=', $fiscalPeriod['id']],
        ['status', '<>', 'cancelled'],
        ['invoice_type', '=', 'expense_statement']
    ])
    ->read([
        'condo_id' => ['name', 'total_shares'],
        'invoice_number',
        'common_total',
        'private_total',
        'provisions_total',
        'statement_owners_ids' => [
            '@domain' => ['ownership_id', '=', $params['ownership_id']],
            'schema'
        ]
    ])
    ->first();

if(!$statement) {
    throw new Exception('no_matching_statement', EQ_ERROR_UNKNOWN_OBJECT);
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

$values = [
        'total_shares'      => $statement['condo_id']['total_shares'],
        'date_from'         => $fiscalPeriod['date_from'],
        'date_to'           => $fiscalPeriod['date_to'],
        'nb_days'           => round(($fiscalPeriod['date_to'] - $fiscalPeriod['date_from']) / 86400, 0) + 1,
        'common_total'      => $statement['common_total'],
        'private_total'     => $statement['private_total'],
        'provisions_total'  => $statement['provisions_total'],
        'owners'            => []
    ];

foreach($statement['statement_owners_ids'] as $statement_owner_id => $statementOwner) {
    $owner = $statementOwner['schema'];
    $owner['expenses'] = $buildOwnerExpenses($owner);
    $values['owners'][] = $owner;
}

if(!count($values['owners'])) {
    throw new Exception('no_matching_owner', EQ_ERROR_UNKNOWN_OBJECT);
}

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
$subject = 'Décompte Propriétaire';
$introduction = '';

$template = Template::search([
        ['code', '=', 'expense_statement'],
        ['type', '=', 'document']
    ])
    ->read(['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_values = [
            'condo'             => $statement['condo_id']['name'],
            'period'            => $getFormattedDate($fiscalPeriod['date_from']) . ' - ' . $getFormattedDate($fiscalPeriod['date_to']),
            'period_from'       => $getFormattedDate($fiscalPeriod['date_from']),
            'period_to'         => $getFormattedDate($fiscalPeriod['date_to'])
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
            'condo'             => $statement['condo_id']['name'],
            'period'            => $getFormattedDate($fiscalPeriod['date_from']) . ' - ' . $getFormattedDate($fiscalPeriod['date_to']),
            'period_from'       => $getFormattedDate($fiscalPeriod['date_from']),
            'period_to'         => $getFormattedDate($fiscalPeriod['date_to'])
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
}

$values = array_merge($values, [
    'title'               => $subject,
    'introduction'        => $introduction,

    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),
    'document_number'     => $statement['invoice_number'],
    'condominium'         => $fiscalPeriod['condo_id'],

    'recipient'           => $owner['identity_id'],
    // #todo - base this on ownership options
    'has_details'         => false,


//    'payment_qr_code_uri' => $getPaymentQrCodeUri($invoice),

    'date'                => time(),
    'timezone'            => constant('L10N_TIMEZONE'),
    'locale'              => constant('L10N_LOCALE'),
    'date_format'         => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),
    'currency'            => $getTwigCurrency(Setting::get_value('core', 'locale', 'currency', '€')),
    'labels'              => $getLabels($lang),
    'debug'               => $params['debug'],

    'fiscal_period'       => [
        'date_from' => $fiscalPeriod['date_from'],
        'date_to'   => $fiscalPeriod['date_to']
    ]
]);


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

    $template = $twig->load('ExpenseStatement.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->body($html)
        ->send();
