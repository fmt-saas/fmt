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
use realestate\funding\PaymentReminder;
use realestate\ownership\Owner;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of a payment reminder for a single ownership.',
    'params'        => [
        'payment_reminder_id' => [
            'description'       => 'Identifier of the specific PaymentReminder to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\PaymentReminder',
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

$dataFormatter = function ($value, $usage) {
    if(is_null($value)) {
        return '';
    }
    return DataFormatter::format($value, $usage);
};

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

$paymentReminder = PaymentReminder::id($params['payment_reminder_id'])
    ->read([
        'name',
        'emission_date',
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
        ],
        'payment_reminder_owners_ids' => [
            '@domain' => ['ownership_id', '=', $params['ownership_id']],
            'due_balance',
            'payment_reminder_owner_lines_ids' => [
                'days_overdue',
                'due_amount',
                'funding_id' => [
                    'name',
                    'funding_type',
                    'due_date',
                    'due_amount',
                    'remaining_amount',
                    'payment_reference',
                    'fund_request_id' => ['name'],
                    'fund_request_execution_id' => ['name'],
                    'expense_statement_id' => ['name'],
                    'purchase_invoice_id' => ['name'],
                    'misc_operation_id' => ['name']
                ]
            ]
        ]
    ])
    ->first();

if(!$paymentReminder) {
    throw new Exception('unknown_payment_reminder', EQ_ERROR_INVALID_PARAM);
}

$paymentReminderOwner = $paymentReminder['payment_reminder_owners_ids']->first(true);

if(!$paymentReminderOwner) {
    throw new Exception('unknown_payment_reminder_owner', EQ_ERROR_INVALID_PARAM);
}

$owner = Owner::search(['ownership_id', '=', $params['ownership_id']])
    ->read([
        'identity_id' => [
            'name', 'firstname', 'lastname', 'address_street', 'address_dispatch', 'address_zip',
            'address_city', 'address_country', 'has_vat', 'vat_number',
            'lang_id' => ['code']
        ]
    ])
    ->first();

if(!$owner) {
    throw new Exception('unknown_owner', EQ_ERROR_INVALID_PARAM);
}

$lang = $owner['identity_id']['lang_id']['code'];

$fundings = [];
$overdue_total = 0.0;

$map_funding_types = [
    'fund_request'       => 'Appel de fonds',
    'expense_statement'  => 'Décompte de charges',
    'purchase_invoice'   => 'Facture',
    'misc_operation'     => 'Opération diverse',
    'statement_line'     => 'Extrait bancaire',
    'due_balance'        => 'Solde copropriétaire'
];

foreach($paymentReminderOwner['payment_reminder_owner_lines_ids'] as $paymentReminderOwnerLine) {
    $funding = $paymentReminderOwnerLine['funding_id'];
    if(!$funding) {
        continue;
    }

    $label = $funding['name'];
    if($funding['fund_request_execution_id']) {
        $label = $funding['fund_request_execution_id']['name'];
    }
    elseif($funding['expense_statement_id']) {
        $label = $funding['expense_statement_id']['name'];
    }
    elseif($funding['purchase_invoice_id']) {
        $label = $funding['purchase_invoice_id']['name'];
    }
    elseif($funding['misc_operation_id']) {
        $label = $funding['misc_operation_id']['name'];
    }
    elseif($funding['fund_request_id']) {
        $label = $funding['fund_request_id']['name'];
    }

    $fundings[] = [
        'nature'            => $map_funding_types[$funding['funding_type']] ?? $funding['funding_type'],
        'label'             => $label,
        'due_date'          => $funding['due_date'],
        'days_overdue'      => $paymentReminderOwnerLine['days_overdue'],
        'due_amount'        => (float) $paymentReminderOwnerLine['due_amount'],
        'remaining_amount'  => (float) ($funding['remaining_amount'] ?? $paymentReminderOwnerLine['due_amount'])
    ];

    $overdue_total += (float) $paymentReminderOwnerLine['due_amount'];
}

usort($fundings, function($a, $b) {
    return ($a['due_date'] <=> $b['due_date']);
});

$owner_balance = (float) ($paymentReminderOwner['due_balance'] ?? $overdue_total);

$organisation = Organisation::id(1)
    ->read([
        'id',
        'name', 'address_street', 'address_dispatch', 'address_zip',
        'address_city', 'address_country', 'has_vat', 'vat_number',
        'legal_name', 'registration_number', 'bank_account_iban', 'bank_account_bic',
        'website', 'email', 'phone', 'has_vat', 'vat_number',
        'profile_image_document_id' => [
            'type', 'data'
        ]
    ])
    ->first();

$subject = 'Rappel de paiement';
$introduction = '';

$template = Template::search([
        ['code', '=', 'payment_reminder_correspondence'],
        ['type', '=', 'document']
    ])
    ->read(['id','parts_ids' => ['name', 'value']])
    ->first(true);

if(!$template) {
    throw new Exception('template_not_found', EQ_ERROR_INVALID_CONFIG);
}

$map_template_values = [
    'condo'         => $paymentReminder['condo_id']['name'],
    'firstname'     => $owner['identity_id']['firstname'] ?? '',
    'lastname'      => $owner['identity_id']['lastname'] ?? '',
    'emission_date' => $getFormattedDate($paymentReminder['emission_date']),
    'due_amount'    => $formatMoney($overdue_total)
];

foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);
        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_template_values) {
            $key = $matches[1];
            return $map_template_values[$key] ?? '';
        }, $subject);
    }
    elseif($part['name'] === 'introduction') {
        $introduction = $part['value'];
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_template_values) {
            $key = $matches[1];
            return $map_template_values[$key] ?? '';
        }, $introduction);
    }
}

$communication = '';
if($owner_balance > 0.01) {
    $communication = sprintf(
        '<p>La situation de votre compte copropriétaire au %s présente un solde débiteur de <strong>%s</strong>.</p><p>Le détail des financements échus repris ci-dessous permet d’identifier l’origine de ce solde.</p>',
        $getFormattedDate($paymentReminder['emission_date']),
        $formatMoney($owner_balance)
    );
}
elseif($owner_balance < -0.01) {
    $communication = sprintf(
        '<p>La situation de votre compte copropriétaire au %s présente un solde créditeur de <strong>%s</strong> en votre faveur.</p>',
        $getFormattedDate($paymentReminder['emission_date']),
        $formatMoney(abs($owner_balance))
    );
}
else {
    $communication = '<p>La situation de votre compte copropriétaire est équilibrée à la date du rappel.</p>';
}

$labels = $getLabels($lang, sprintf('%s/packages/realestate/i18n/%s/funding/%s.json', EQ_BASEDIR, $lang, 'PaymentReminder.' . $params['view_id']));

$values = [
    'title'               => $subject,
    'introduction'        => $introduction,
    'communication'       => $communication,
    'organisation'        => $organisation,
    'organisation_logo'   => $getOrganisationLogo($organisation['id']),
    'condominium'         => $paymentReminder['condo_id'],
    'recipient'           => $owner['identity_id'],
    'payment_reminder'    => $paymentReminder,
    'owner_balance'       => $owner_balance,
    'overdue_total'       => $overdue_total,
    'fundings'            => $fundings,
    'date'                => time(),
    'timezone'            => constant('L10N_TIMEZONE'),
    'locale'              => constant('L10N_LOCALE'),
    'date_format'         => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),
    'currency'            => $getTwigCurrency(Setting::get_value('core', 'locale', 'currency', '€')),
    'labels'              => $labels,
    'debug'               => $params['debug']
];

try {
    $loader = new TwigFilesystemLoader([
            EQ_BASEDIR.'/packages/realestate/views/_parts',
            EQ_BASEDIR.'/packages/realestate/views/funding'
        ]);

    $twig = new TwigEnvironment($loader);

    /** @var ExtensionInterface $extension **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $twig->addFilter(
        new TwigFilter('format_money', $formatMoney)
    );

    $twig->addFilter(
        new TwigFilter('data_format', $dataFormatter)
    );

    $template = $twig->load('PaymentReminder.' . $params['view_id'] . '.html');
    $html = $template->render($values);
}
catch(\Throwable $e) {
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('rendering_error', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
    ->body($html)
    ->send();
