<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use core\setting\Setting;
use identity\Organisation;
use realestate\ownership\Ownership;
use realestate\property\Condominium;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate HTML code of the owner account statement for a given ownership and date range.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => "The ownership that the owner refers to.",
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval.",
            'required'          => true
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'required'          => true
        ],

        'debug' => [
            'type'        => 'boolean',
            'default'     => false
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


$ownership = Ownership::id($params['ownership_id'])
    ->read(['condo_id'])
    ->first();

if(!$ownership) {
        throw new \Exception('unknown_ownership', EQ_ERROR_INVALID_PARAM);
}

$condominium = Condominium::id($ownership['condo_id'])
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

$data = eQual::run('get', 'finance_accounting_ownerAccountStatement_collect', [
        'ownership_id'      => $params['ownership_id'],
        'date_from'         => $params['date_from'],
        'date_to'           => $params['date_to'],
    ]);

$total_debit = 0;
$total_credit = 0;

foreach($data as $line) {
    $total_debit += $line['debit'];
    $total_credit += $line['credit'];
}

$closing_balance = end($data)['balance'] ?? 0;

$values = [
    'title'               => 'Détail de votre compte propriétaire',

    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),

    'condominium'         => $condominium,

    'lines'               => $data,
    'total_debit'         => $total_debit,
    'total_credit'        => $total_credit,
    'closing_balance'     => $closing_balance,

    'date'                => $params['date_to'],
    'timezone'            => constant('L10N_TIMEZONE'),
    'locale'              => constant('L10N_LOCALE'),
    'date_format'         => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),
    'currency'            => $getTwigCurrency(Setting::get_value('core', 'locale', 'currency', '€')),
    'labels'              => $getLabels($lang),
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

    $template = $twig->load('ownerAccountStatement.print.default.html');
    $html = $template->render($values);

}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->body($html)
        ->send();