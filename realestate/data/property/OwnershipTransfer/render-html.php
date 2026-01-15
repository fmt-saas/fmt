<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use core\setting\Setting;
use equal\data\DataFormatter;
use identity\Organisation;
use realestate\property\NotaryOffice;
use realestate\property\OwnershipTransfer;
use realestate\sale\pay\Funding;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of given  Ownership transfer.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Ownership transfer that must be returned.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\property\OwnershipTransfer',
            'required'          => true
        ],

        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ],

        'view_id' => [
            'description' => 'View id of the template to use.',
            'type'        => 'string',
            'default'     => 'print.notary'
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
        'charset'       => 'utf-8',
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


$ownershipTransfer = OwnershipTransfer::id($params['id'])
    ->read([
        'status',
        'condo_shares',
        'ownership_shares',
        'is_notary_request',
        'request_contact_name',
        'request_contact_address_street',
        'request_contact_address_zip',
        'request_contact_address_city',
        'request_contact_email',
        'request_notary_office_id',
        'request_date',
        'confirmation_notary_office_id',
        'has_intervention_record',
        'has_fuel_tank',
        'fuel_tank_capacity',
        'condo_id' => [
            'name', 'address_street', 'address_city', 'address_zip', 'address_city',
            'registration_number'
        ],
        'old_ownership_id' => ['name', 'owners_ids' => ['name']],
        'property_lots_ids' => ['name'],
        'fund_balances_ids' => [
            'condo_fund_id' => ['name'],
            'condo_fund_balance',
            'condo_fund_shares',
            'property_lots_shares',
            'property_lots_amount'
        ],
        'fund_requests_ids' => [
            'fund_request_id' => ['name'],
            'condo_called_amount',
            'condo_planned_amount',
            'property_lots_called_amount',
            'property_lots_planned_amount'
        ],
        'transfer_fees_ids' => [
            'fee_date', 'description', 'price'
        ],
        // 3.94.1.1
        'fund_balances_description',
        // 3.94.1.2
        'seller_arrears_description',
        // 3.94.1.3
        'scheduled_fund_requests_description',
        // 3.94.1.4
        'judiciary_procedures_description',
        // 3.94.1.5
        'general_assembly_minutes_description',
        // 3.94.1.6
        'latest_balance_sheet_description',
        // 3.94.2.1
        'maintenance_expenses_description',
        // 3.94.2.2
        'fund_requests_description',
        // 3.94.2.3
        'commons_acquisitions_description',
        // 3.94.2.4
        'condominium_debts_description'
    ])
    ->first(true);

if(!$ownershipTransfer) {
    throw new Exception('unknown_ownership_transfer', EQ_ERROR_UNKNOWN_OBJECT);
}

$arrear_fundings = Funding::search([ ['condo_id', '=', $ownershipTransfer['condo_id']['id']], ['is_paid', '=', false], ['ownership_id', '=', $ownershipTransfer['old_ownership_id']['id']]])
    ->read(['due_date', 'name', 'funding_type', 'remaining_amount'])
    ->get(true);

$lang = $params['lang'];

// values to fetch from Condominium
// expense_management_mode
$organisation = Organisation::search()
    ->read([
        'name', 'address_street', 'address_dispatch', 'address_zip',
        'address_city', 'address_country', 'has_vat', 'vat_number',
        'legal_name', 'registration_number', 'bank_account_iban', 'bank_account_bic',
        'website', 'email', 'phone', 'has_vat', 'vat_number',
        'profile_image_document_id' => [
            'type', 'data'
        ]
    ])
    ->first(true);


$organisation['bank_account_iban'] = DataFormatter::format($organisation['bank_account_iban'], 'iban');
$organisation['phone'] = DataFormatter::format($organisation['phone'], 'phone');

// compute contact details
$request_contact_name = $ownershipTransfer['request_contact_name'];
$request_contact_address_street = $ownershipTransfer['request_contact_address_street'];
$request_contact_address_zip = $ownershipTransfer['request_contact_address_zip'];
$request_contact_address_city = $ownershipTransfer['request_contact_address_city'];
$request_contact_email = $ownershipTransfer['request_contact_email'];

if(in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent']) && $ownershipTransfer['is_notary_request']) {
    $notaryOffice = NotaryOffice::id($ownershipTransfer['request_notary_office_id'])
        ->read(['name', 'address_street', 'address_zip', 'address_city', 'email'])
        ->first();
    $request_contact_name = $notaryOffice['name'];
    $request_contact_address_street = $notaryOffice['address_street'];
    $request_contact_address_zip = $notaryOffice['address_zip'];
    $request_contact_address_city = $notaryOffice['address_city'];
    $request_contact_email = $notaryOffice['email'];
}
elseif($ownershipTransfer['confirmation_notary_office_id']) {
    $notaryOffice = NotaryOffice::id($ownershipTransfer['confirmation_notary_office_id'])
        ->read(['name', 'address_street', 'address_zip', 'address_city', 'email'])
        ->first();
    $request_contact_name = $notaryOffice['name'];
    $request_contact_address_street = $notaryOffice['address_street'];
    $request_contact_address_zip = $notaryOffice['address_zip'];
    $request_contact_address_city = $notaryOffice['address_city'];
    $request_contact_email = $notaryOffice['email'];
}


$values = [
    'organisation'              => $organisation,
    'condominium'               => $ownershipTransfer['condo_id'],
    'property_lots'             => $ownershipTransfer['property_lots_ids'],
    'funds_balances'            => $ownershipTransfer['fund_balances_ids'],
    'funds_requests'            => $ownershipTransfer['fund_requests_ids'],
    'arrear_fundings'           => $arrear_fundings,
    'transfer_fees'             => $ownershipTransfer['transfer_fees_ids'],
    'ownership'                 => $ownershipTransfer['old_ownership_id'],
    'ownership_shares'          => $ownershipTransfer['ownership_shares'],
    'condo_shares'              => $ownershipTransfer['condo_shares'],
    'has_intervention_record'   => $ownershipTransfer['has_intervention_record'],
    'has_fuel_tank'             => $ownershipTransfer['has_fuel_tank'],
    'fuel_tank_capacity'        => $ownershipTransfer['fuel_tank_capacity'],
    'request_date'              => $ownershipTransfer['request_date'],

    'request_contact_name'              => $request_contact_name,
    'request_contact_address_street'    => $request_contact_address_street,
    'request_contact_address_zip'       => $request_contact_address_zip,
    'request_contact_address_city'      => $request_contact_address_city,
    'request_contact_email'             => $request_contact_email,

    'today_date'                => time(),
    'timezone'                  => constant('L10N_TIMEZONE'),
    'locale'                    => constant('L10N_LOCALE'),
    'date_format'               => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),
    'currency'                  => $getTwigCurrency(Setting::get_value('core', 'locale', 'currency', '€')),
    'labels'                    => $getLabels($lang),
    'debug'                     => $params['debug'],
    // 3.94.1.1
    'fund_balances_description'             => $ownershipTransfer['fund_balances_description'],
    // 3.94.1.2
    'seller_arrears_description'            => $ownershipTransfer['seller_arrears_description'],
    // 3.94.1.3
    'scheduled_fund_requests_description'   => $ownershipTransfer['scheduled_fund_requests_description'],
    // 3.94.1.4
    'judiciary_procedures_description'      => $ownershipTransfer['judiciary_procedures_description'],
    // 3.94.1.5
    'general_assembly_minutes_description'  => $ownershipTransfer['general_assembly_minutes_description'],
    // 3.94.1.6
    'latest_balance_sheet_description'      => $ownershipTransfer['latest_balance_sheet_description'],
    // 3.94.2.1
    'maintenance_expenses_description'      => $ownershipTransfer['maintenance_expenses_description'],
    // 3.94.2.2
    'fund_requests_description'             => $ownershipTransfer['fund_requests_description'],
    // 3.94.2.3
    'commons_acquisitions_description'      => $ownershipTransfer['commons_acquisitions_description'],
    // 3.94.2.4
    'condominium_debts_description'         => $ownershipTransfer['condominium_debts_description'],
];





try {
    // generate HTML
    $loader = new TwigFilesystemLoader([
            EQ_BASEDIR.'/packages/realestate/views/_parts',
            EQ_BASEDIR.'/packages/realestate/views/property'
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

    $template = $twig->load('OwnershipTransfer.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();
