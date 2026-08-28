<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\setting\Setting;
use identity\Identity;
use identity\Organisation;
use realestate\ownership\Owner;
use realestate\property\transfer\OwnershipTransferSettlementCorrespondence;
use Twig\Environment as TwigEnvironment;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description' => 'Render the seller or buyer ownership-transfer settlement correspondence as HTML.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
            'description'    => 'Settlement correspondence to render.',
            'required'       => true
        ],
        'debug' => [
            'type'    => 'boolean',
            'default' => false
        ]
    ],
    'access'    => ['visibility' => 'protected'],
    'response'  => [
        'content-type'  => 'text/html',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context'],
    'constants' => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

['context' => $context] = $providers;

$correspondence = OwnershipTransferSettlementCorrespondence::id($params['id'])
    ->read([
        'recipient_role',
        'owner_id',
        'ownership_id' => ['name', 'code'],
        'settlement_id' => [
            'name',
            'transfer_date',
            'validated_at',
            'seller_net_amount',
            'buyer_net_amount',
            'ownership_transfer_id',
            'condo_id' => [
                'name',
                'address_street',
                'address_zip',
                'address_city',
                'registration_number'
            ],
            'lines_ids' => [
                'source_type',
                'correction_type',
                'condo_fund_id'             => ['name'],
                'fund_request_execution_id' => ['name'],
                'expense_statement_id'      => ['name'],
                'property_lot_id'            => ['name'],
                'period_from',
                'period_to',
                'applied_amount'
            ]
        ]
    ])
    ->first(true);

if(!$correspondence) {
    throw new Exception('unknown_settlement_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}

if(!in_array($correspondence['recipient_role'], ['seller', 'buyer'], true)) {
    throw new Exception('invalid_correspondence_recipient_role', EQ_ERROR_INVALID_CONFIG);
}

$owner = Owner::id($correspondence['owner_id'])
    ->read(['firstname', 'lastname', 'email', 'identity_id' => ['lang_id' => ['code']]])
    ->first();

if(!$owner || !$owner['identity_id']) {
    throw new Exception('unknown_correspondence_recipient', EQ_ERROR_INVALID_CONFIG);
}

$lang = $owner['identity_id']['lang_id']['code'] ?? 'fr';
$identity = Identity::id($owner['identity_id']['id'])
    ->read([
        'firstname',
        'lastname',
        'title',
        'email',
        'address_street',
        'address_dispatch',
        'address_zip',
        'address_city',
        'address_country',
        'has_vat',
        'vat_number'
    ], $lang)
    ->first();

if(!$identity) {
    throw new Exception('unknown_correspondence_recipient', EQ_ERROR_INVALID_CONFIG);
}

$title = $identity['title'] ?? '';
try {
    $identityI18n = eQual::run('get', 'core_config_i18n', [
        'entity' => 'identity\Identity',
        'lang'   => $lang
    ]);
    $title = $identityI18n['model']['title']['selection'][$title] ?? $title;
}
catch(Throwable $e) {
    // Keep the stored title when translations are unavailable.
}

$recipient = [
    'name'             => trim($title . ' ' . ucfirst($identity['firstname']) . ' ' . strtoupper($identity['lastname'])),
    'email'            => $identity['email'],
    'address_street'   => $identity['address_street'],
    'address_dispatch' => $identity['address_dispatch'],
    'address_zip'      => $identity['address_zip'],
    'address_city'     => $identity['address_city'],
    'address_country'  => $identity['address_country'],
    'has_vat'          => $identity['has_vat'],
    'vat_number'       => $identity['vat_number']
];

$organisation = Organisation::id(1)
    ->read([
        'legal_name',
        'address_street',
        'address_zip',
        'address_city',
        'registration_number',
        'bank_account_iban',
        'email',
        'website',
        'phone',
        'fax',
        'profile_image_print'
    ])
    ->first();

if(!$organisation) {
    throw new Exception('missing_organisation', EQ_ERROR_INVALID_CONFIG);
}

$organisation_logo = '';
if($organisation['profile_image_print']) {
    $organisation_logo = 'data:image/jpeg;base64,' . base64_encode($organisation['profile_image_print']);
}

$settlement = $correspondence['settlement_id'];
$lines = [];
foreach($settlement['lines_ids'] as $line) {
    $source_name = '';
    if($line['source_type'] === 'working_fund') {
        $source_name = $line['condo_fund_id']['name'] ?? '';
    }
    elseif($line['source_type'] === 'fund_request_execution') {
        $source_name = $line['fund_request_execution_id']['name'] ?? '';
    }
    elseif($line['source_type'] === 'expense_statement') {
        $source_name = $line['expense_statement_id']['name'] ?? '';
    }

    $lines[] = [
        'source_type'     => $line['source_type'],
        'source_name'     => $source_name,
        'correction_type' => $line['correction_type'],
        'property_lot'    => $line['property_lot_id']['name'] ?? '',
        'period_from'     => $line['period_from'],
        'period_to'       => $line['period_to'],
        'applied_amount'  => number_format((float) $line['applied_amount'], 2, ',', '.') . ' €'
    ];
}

$labels = [];
$label_files = [
    EQ_BASEDIR . "/packages/realestate/i18n/{$lang}/_parts/header.json",
    EQ_BASEDIR . "/packages/realestate/i18n/{$lang}/_parts/footer.json",
    EQ_BASEDIR . "/packages/realestate/i18n/{$lang}/property/transfer/OwnershipTransferSettlementCorrespondence.print.{$correspondence['recipient_role']}.json"
];

foreach($label_files as $label_file) {
    if(!is_file($label_file)) {
        throw new Exception('missing_correspondence_labels', EQ_ERROR_INVALID_CONFIG);
    }
    $labels = array_merge($labels, json_decode(file_get_contents($label_file), true, 512, JSON_THROW_ON_ERROR));
}

$amount = $correspondence['recipient_role'] === 'seller'
    ? $settlement['seller_net_amount']
    : $settlement['buyer_net_amount'];

$values = [
    'debug'             => $params['debug'],
    'title'             => $labels['title'],
    'date'              => $settlement['validated_at'] ?: time(),
    'date_format'       => Setting::get_value('core', 'locale', 'date_format', 'd/m/Y'),
    'timezone'          => constant('L10N_TIMEZONE'),
    'locale'            => constant('L10N_LOCALE'),
    'labels'            => $labels,
    'organisation'      => $organisation,
    'organisation_logo' => $organisation_logo,
    'condominium'       => $settlement['condo_id'],
    'recipient'         => $recipient,
    'ownership'         => $correspondence['ownership_id'],
    'settlement'        => $settlement,
    'lines'             => $lines,
    'net_amount'        => number_format((float) $amount, 2, ',', '.') . ' €'
];

try {
    $loader = new TwigFilesystemLoader([
        EQ_BASEDIR . '/packages/realestate/views/_parts',
        EQ_BASEDIR . '/packages/realestate/views/property/transfer'
    ]);
    $twig = new TwigEnvironment($loader);
    $twig->addExtension(new IntlExtension());
    $template = $twig->load(
        'OwnershipTransferSettlementCorrespondence.print.' . $correspondence['recipient_role'] . '.html'
    );
    $html = $template->render($values);
}
catch(Throwable $e) {
    trigger_error('APP::Unable to render ownership transfer settlement correspondence: ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('settlement_correspondence_rendering_failed', EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();

