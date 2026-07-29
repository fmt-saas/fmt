<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use fmt\setting\Setting;
use equal\data\DataFormatter;
use identity\Organisation;
use realestate\property\Condominium;
use realestate\property\NotaryOffice;
use realestate\property\OwnershipTransfer;
use realestate\property\OwnershipTransferArrearLine;
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

$labels = $getLabels(
    $params['lang'],
    sprintf('%s/packages/realestate/i18n/%s/property/%s.json', EQ_BASEDIR, $params['lang'], 'OwnershipTransfer.'.$params['view_id']),
    [
        'letter.date_line'                                                        => '%s, %s',
        'letter.subject.label'                                                    => 'Subject',
        'letter.subject.text'                                                     => 'Your request for information / Agreement for the transfer of ownership rights',
        'letter.closing'                                                          => 'We trust that we have answered your questions and remain at your disposal for any further information. Yours faithfully.',
        'letter.signature'                                                        => 'The Managing Agent',

        'condominium_association.label'                                           => 'Association of Co-owners',
        'sale_by.label'                                                           => 'Sold by',
        'lots.label'                                                              => 'Lot(s)',
        'total_shares.label'                                                      => 'Total shares',
        'introduction.request_received'                                           => 'We acknowledge receipt of your letter dated %s and ask you to find the requested information below.',

        'article_3_94.section_1.title'                                            => 'In accordance with article 3.94, paragraph 1',
        'article_3_94.section_1.fund_balances.title'                              => '1. the amount of the working capital fund and reserve fund, within the meaning of paragraph 5, subparagraphs 2 and 3;',
        'article_3_94.section_1.seller_arrears.title'                             => '2. the amount of any arrears owed by the transferor;',
        'article_3_94.section_1.scheduled_fund_requests.title'                    => '3. the status of fund calls intended for the reserve fund and decided by the general meeting before the certain date of transfer of ownership;',
        'article_3_94.section_1.judiciary_procedures.title'                       => '4. where applicable, the list of ongoing judicial proceedings relating to the co-ownership;',
        'article_3_94.section_1.general_assembly_minutes.title'                   => '5. the minutes of ordinary and extraordinary general meetings for the last three years, as well as periodic charge statements for the last two years;',
        'article_3_94.section_1.latest_balance_sheet.title'                       => '6. a copy of the latest balance sheet approved by the general meeting',

        'article_3_94.section_2.title'                                            => 'In accordance with article 3.94, paragraph 2',
        'article_3_94.section_2.maintenance_expenses.title'                       => '1. the amount of preservation, maintenance, repair and renovation expenses decided by the general meeting or the managing agent before the certain date of transfer of ownership but requested by the managing agent after that date;',
        'article_3_94.section_2.fund_requests.title'                              => '2. a statement of fund calls approved by the general meeting of co-owners before the certain date of transfer of ownership and the cost of urgent works for which payment is requested by the managing agent after that date;',
        'article_3_94.section_2.commons_acquisitions.title'                       => '3. a statement of costs relating to the acquisition of common parts, decided by the general meeting before the certain date of transfer of ownership, but for which payment is requested by the managing agent after that date;',
        'article_3_94.section_2.condominium_debts.title'                          => '4. a statement of certain debts owed by the association of co-owners following disputes arising before the certain date of transfer of ownership, but for which payment is requested by the managing agent after that date.',
        'article_3_94.section_2.seller_arrears.title'                             => '5. the amount of any arrears owed by the transferor;',

        'article_3_94.section_3.title'                                            => 'In accordance with article 3.94, paragraph 3',
        'article_3_94.section_3.notary_information_notice'                        => 'In the event of transfer or dismemberment of the ownership right over a private lot, the executing notary informs the managing agent of the date of execution of the deed, the identification of the private lot concerned, and the current and, where applicable, future identity and address of the persons concerned.',
        'article_3_94.section_3.change_notification_request'                      => 'Please notify us of these changes as soon as possible.',

        'fund_balances.table.th.fund'                                             => 'Fund',
        'fund_balances.table.th.lot_shares'                                       => 'Lot shares',
        'fund_balances.table.th.condo_shares'                                     => 'Building shares',
        'fund_balances.table.th.condominium'                                      => 'Co-ownership',
        'fund_balances.table.th.owner_share'                                      => 'Co-owner share',
        'bank_loans.table.th.description'                                         => 'Loan',
        'bank_loans.table.th.apportionment'                                       => 'Apportionment',
        'bank_loans.table.th.lot'                                                 => 'Lot',
        'bank_loans.table.th.lot_shares'                                          => 'Lot shares',
        'bank_loans.table.th.condo_shares'                                        => 'Building shares',
        'bank_loans.table.th.total_amount'                                        => 'Loan balance',
        'bank_loans.table.th.property_lot_amount'                                 => 'Lot balance',

        'arrears.table.th.due_date'                                               => 'Due date',
        'arrears.table.th.label'                                                  => 'Label',
        'arrears.table.th.type'                                                   => 'Type',
        'arrears.table.th.remaining'                                              => 'Remaining',
        'arrears.penalties_notice'                                                => 'These amounts do not include increases due to financial penalties, interest, costs and expenses, whether resulting from the co-ownership statutes, decisions of general meetings or court decisions. The final calculation can only be made on the day the amounts due are received.',
        'arrears.additional_provisions_notice'                                    => '*Additional provisions are claimed in the name and on behalf of the co-ownership to ensure that no potentially significant adjustment statement has to be claimed in favor of the co-ownership; this amount is calculated on an average of 3 months of charges.',

        'transfer_fees.table.th.file_fees'                                        => 'File fees (3.94 paragraph 4)',
        'transfer_fees.table.th.date'                                             => 'Date',
        'transfer_fees.table.th.description'                                      => 'Description',
        'transfer_fees.table.th.amount'                                           => 'Amount',

        'fund_requests.table.th.call'                                             => 'Call',
        'fund_requests.table.th.called'                                           => 'Already called',
        'fund_requests.table.th.planned'                                          => 'Planned',
        'fund_requests.table.th.owner_called'                                     => 'Called owner share',
        'fund_requests.table.th.owner_planned'                                    => 'Planned owner share',
        'fund_requests.last_ago_notice'                                           => 'Please refer to the minutes of the latest ordinary general meeting for the terms and due dates of these calls.',

        'payment.current_fiscal_year_notice'                                      => 'This amount does not include the charge statement for the current fiscal year.',
        'payment.account_instruction'                                             => 'All payments must be made to the account of the Association of Co-owners',
        'payment.reference_instruction'                                           => 'with the stated structured reference.',

        'additional_information.title'                                            => 'Additional information',
        'additional_information.bank_loans'                                       => 'Bank loan(s)',
        'additional_information.intervention_record.title'                        => 'Post-intervention file',
        'additional_information.intervention_record.exists'                       => 'A post-intervention file exists.',
        'additional_information.intervention_record.missing'                      => 'No file is in the possession of the managing agent.',
        'additional_information.intervention_record.consultation_notice'          => 'We offer future buyers, insofar as they are interested and insofar as this file exists, the opportunity to consult it at our offices by prior appointment.',
        'additional_information.fuel_tank.title'                                  => 'Fuel oil tank',
        'additional_information.fuel_tank.exists'                                 => 'There is a tank (estimated capacity of +/- %s liters)',
        'additional_information.fuel_tank.missing'                                => 'There is no fuel oil tank'
    ]
);

$ownershipTransfer = OwnershipTransfer::id($params['id'])
    ->read([
        'status',
        'condo_id',
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
        'with_additional_info_1',
        'with_additional_info_2',
        'has_intervention_record',
        'has_fuel_tank',
        'fuel_tank_capacity',
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
        'bank_loan_lines_ids' => [
            'description',
            'apportionment_id' => ['name', 'code', 'description'],
            'property_lot_id' => ['name', 'code', 'property_lot_ref'],
            'property_lot_shares',
            'total_shares',
            'total_amount',
            'property_lot_amount'
        ],
        'transfer_fees_ids' => [
            'fee_date', 'description', 'price'
        ],
        'with_both_paragraphs',
        'bank_loan_description',
        // 3.94.1.1
        'fund_balances_description',
        // 3.94.1.2
        'has_seller_arrears_1',
        'seller_arrears_description_1',
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
        'condominium_debts_description',
        // 3.94.2.5
        'has_seller_arrears_2',
        'seller_arrears_description_2'
    ])
    ->first(true);

if(!$ownershipTransfer) {
    throw new Exception('unknown_ownership_transfer', EQ_ERROR_UNKNOWN_OBJECT);
}

$arrear_lines_1 = OwnershipTransferArrearLine::search([
        ['condo_id', '=', $ownershipTransfer['condo_id']],
        ['arrear_paragraph', '=', '1'],
        ['ownership_transfer_id', '=', $ownershipTransfer['id']]
    ])
    ->read(['due_date', 'description', 'arrear_line_type', 'due_amount'])
    ->get(true);

$arrear_lines_1 = OwnershipTransferArrearLine::search([
        ['condo_id', '=', $ownershipTransfer['condo_id']],
        ['arrear_paragraph', '=', '1'],
        ['ownership_transfer_id', '=', $ownershipTransfer['id']]
    ])
    ->read(['due_date', 'description', 'arrear_line_type', 'due_amount'])
    ->get(true);

$arrear_lines_2 = OwnershipTransferArrearLine::search([
        ['condo_id', '=', $ownershipTransfer['condo_id']],
        ['arrear_paragraph', '=', '2'],
        ['ownership_transfer_id', '=', $ownershipTransfer['id']]
    ])
    ->read(['due_date', 'description', 'arrear_line_type', 'due_amount'])
    ->get(true);

$lang = $params['lang'];

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

$condominium = Condominium::id($ownershipTransfer['condo_id'])
    // primary condominium Bank Account
    ->read([
        'name', 'address_street', 'address_city', 'address_zip', 'address_city',
        'bank_account_iban', 'bank_account_bic',
        'registration_number'
    ])
    ->first();

$condominium['bank_account_iban'] = DataFormatter::format($condominium['bank_account_iban'], 'iban');

// compute contact details
$request_contact_name = $ownershipTransfer['request_contact_name'];
$request_contact_address_street = $ownershipTransfer['request_contact_address_street'];
$request_contact_address_zip = $ownershipTransfer['request_contact_address_zip'];
$request_contact_address_city = $ownershipTransfer['request_contact_address_city'];
$request_contact_email = $ownershipTransfer['request_contact_email'];

if(in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'])) {
    if($ownershipTransfer['is_notary_request']) {
        $notaryOffice = NotaryOffice::id($ownershipTransfer['request_notary_office_id'])
            ->read(['name', 'address_street', 'address_zip', 'address_city', 'email'])
            ->first();
        $request_contact_name = $notaryOffice['name'];
        $request_contact_address_street = $notaryOffice['address_street'];
        $request_contact_address_zip = $notaryOffice['address_zip'];
        $request_contact_address_city = $notaryOffice['address_city'];
        $request_contact_email = $notaryOffice['email'];
    }
}

if($ownershipTransfer['is_notary_request']) {
    if(in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'])) {
        $notaryOffice = NotaryOffice::id($ownershipTransfer['request_notary_office_id'])
            ->read(['name', 'address_street', 'address_zip', 'address_city', 'email'])
            ->first();
        $request_contact_name = $notaryOffice['name'];
        $request_contact_address_street = $notaryOffice['address_street'];
        $request_contact_address_zip = $notaryOffice['address_zip'];
        $request_contact_address_city = $notaryOffice['address_city'];
        $request_contact_email = $notaryOffice['email'];
    }
}

if(in_array($ownershipTransfer['status'], ['confirmed', 'financial_statement_sent', 'settled', 'closed'])) {
    if($ownershipTransfer['confirmation_notary_office_id']) {
        $notaryOffice = NotaryOffice::id($ownershipTransfer['confirmation_notary_office_id'])
            ->read(['name', 'address_street', 'address_zip', 'address_city', 'email'])
            ->first();
        $request_contact_name = $notaryOffice['name'];
        $request_contact_address_street = $notaryOffice['address_street'];
        $request_contact_address_zip = $notaryOffice['address_zip'];
        $request_contact_address_city = $notaryOffice['address_city'];
        $request_contact_email = $notaryOffice['email'];
    }
}

if(!in_array($ownershipTransfer['status'], ['pending', 'open', 'seller_documents_sent'], true)) {

    $template = Template::search([
            ['code', '=', 'ownership_transfer_paragraph_1'],
            ['type', '=', 'document']
        ])
        ->read( ['id', 'parts_ids' => ['name', 'value']])
        ->first(true);

    foreach($template['parts_ids'] as $part_id => $part) {
        if($part['name'] === 'refer_to_paragraph_2') {
            $ownershipTransfer['seller_arrears_description_1'] = $part['value'];
        }
    }
}

$recipient = [
    'name'              => $request_contact_name,
    'address_street'    => $request_contact_address_street,
    'address_dispatch'  => '',
    'address_zip'       => $request_contact_address_zip,
    'address_city'      => $request_contact_address_city,
    'address_country'   => 'BE',
    'email'             => $request_contact_email,
];

$values = [

    'organisation'                          => $organisation,
    'organisation_logo'                     => $getOrganisationLogo($organisation['id']),
    'condominium'                           => $condominium['condo_id'],

    'property_lots'                         => $ownershipTransfer['property_lots_ids'],
    'funds_balances'                        => $ownershipTransfer['fund_balances_ids'],
    'funds_requests'                        => $ownershipTransfer['fund_requests_ids'],
    'bank_loans'                            => $ownershipTransfer['bank_loan_lines_ids'],
    'arrear_lines_1'                        => $arrear_lines_1,
    'arrear_lines_2'                        => $arrear_lines_2,

    'transfer_fees'                         => $ownershipTransfer['transfer_fees_ids'],
    'ownership'                             => $ownershipTransfer['old_ownership_id'],
    'ownership_shares'                      => $ownershipTransfer['ownership_shares'],
    'condo_shares'                          => $ownershipTransfer['condo_shares'],

    'with_additional_info_1'                => $ownershipTransfer['with_additional_info_1'],
    'with_additional_info_2'                => $ownershipTransfer['with_additional_info_2'],
    'has_intervention_record'               => $ownershipTransfer['has_intervention_record'],
    'has_fuel_tank'                         => $ownershipTransfer['has_fuel_tank'],
    'fuel_tank_capacity'                    => $ownershipTransfer['fuel_tank_capacity'],
    'request_date'                          => $ownershipTransfer['request_date'],
    'status'                                => $ownershipTransfer['status'],

    'recipient'                             => $recipient,

    'today_date'                            => time(),
    'timezone'                              => constant('L10N_TIMEZONE'),
    'locale'                                => constant('L10N_LOCALE'),
    'date_format'                           => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),
    'currency'                              => $getTwigCurrency(Setting::get_value('core', 'locale', 'currency', '€')),
    'labels'                                => $labels,
    'debug'                                 => $params['debug'],
    // 3.94.1.1
    'fund_balances_description'             => $ownershipTransfer['fund_balances_description'],
    // 3.94.1.2
    'has_seller_arrears_1'                  => $ownershipTransfer['has_seller_arrears_1'],
    'seller_arrears_description_1'          => $ownershipTransfer['seller_arrears_description_1'],
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
    // 3.94.2.5
    'has_seller_arrears_2'                  => $ownershipTransfer['has_seller_arrears_2'],
    'seller_arrears_description_2'          => $ownershipTransfer['seller_arrears_description_2'],
    // additional
    'bank_loan_description'                 => $ownershipTransfer['bank_loan_description'],
    'with_both_paragraphs'                  => $ownershipTransfer['with_both_paragraphs']
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
