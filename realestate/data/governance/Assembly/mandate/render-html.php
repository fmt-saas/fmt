<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\setting\Setting;
use identity\Organisation;
use realestate\governance\Assembly;
use realestate\ownership\Ownership;
use realestate\property\PropertyLotOwnership;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of a Mandate template.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'ownership_id' => [
            'description'       => 'Identifier of the Ownership for whom the mandate is requested.',
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


$assembly = Assembly::id($params['id'])
    ->read([
        'name',
        'condo_id',
        'assembly_type',
        'assembly_date',
        'assembly_location',
        'ownerships_ids',
        'condo_id' => [
            'name', 'address', 'address_street', 'address_zip', 'address_city',
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
        ],
    ])
    ->first(true);

if(!$assembly) {
    throw new Exception('unknown_assembly', EQ_ERROR_UNKNOWN_OBJECT);
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

if(!in_array($params['ownership_id'], $assembly['ownerships_ids'])) {
    throw new Exception('ownership_not_part_of_assembly', EQ_ERROR_INVALID_PARAM);
}

$ownership = Ownership::id($params['ownership_id'])
    ->read(['representative_owner_id' => ['name', 'address']])
    ->first(true);

if(!$ownership) {
    throw new Exception('unknown_ownership', EQ_ERROR_UNKNOWN_OBJECT);
}

// retrieve owners, lots and shares
$property_lots = [];

$propertyLotOwnerships = PropertyLotOwnership::search([
        ['condo_id', '=', $assembly['condo_id']['id']],
        ['ownership_id', '=', $ownership['id']]
    ])
    ->read([
        'date_to',
        'property_lot_id' => ['name', 'code', 'is_primary']
    ]);

foreach($propertyLotOwnerships as $propertyLotOwnership) {
    if((!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assembly['assembly_date']) && $propertyLotOwnership['property_lot_id']['is_primary']) {
        $property_lots[] = $propertyLotOwnership['property_lot_id']['name'];
    }
}

$subject = 'Procuration';
$owner_undersign = '';
$owner_representation = '';
$notice = '';

$template = Template::search([
    ['code', '=', 'mandate_form'],
    ['type', '=', 'document']
])
    ->read(['id','parts_ids' => ['name', 'value']])
    ->first(true); // owner_undersign ["representative_owner", "representative_owner_address", "condo"], owner_representation ["assembly_date", "assembly_location"], notice

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);
    }
    elseif($part['name'] == 'owner_undersign') {
        $owner_undersign = $part['value'];

        $map_values = [
            'representative_owner'          => $ownership['representative_owner_id']['name'],
            'representative_owner_address'  => $ownership['representative_owner_id']['address'],
            'condo'                         => $assembly['condo_id']['name']
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $owner_undersign = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $owner_undersign);
    }
    elseif($part['name'] == 'owner_representation') {
        $owner_representation = $part['value'];

        $map_values = [
            'assembly_date'     => date('d/m/Y', $assembly['assembly_date']),
            'assembly_location' => $assembly['assembly_location']
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $owner_representation = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $owner_representation);
    }
    elseif($part['name'] == 'notice') {
        $notice = $part['value'];
    }
}

$labels = $getLabels(
    $params['lang'],
    sprintf('%s/packages/realestate/i18n/%s/governance/%s.json', EQ_BASEDIR, $params['lang'], 'AssemblyMandate.'.$params['view_id'])
);

$values = [
    'title'                     => $subject,
    'owner_undersign'           => $owner_undersign,
    'owner_representation'      => $owner_representation,
    'notice'                    => $notice,

    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $organisation,
    'organisation_logo'         => $getOrganisationLogo($organisation['id']),

    'ownership'                 => $ownership,
    'property_lots'             => $property_lots,

    // 'today_date'                => time(),
    'timezone'                  => constant('L10N_TIMEZONE'),
    'locale'                    => constant('L10N_LOCALE'),
    'date_format'               => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),

    'labels'                    => $labels,
    'debug'                     => $params['debug']
];


try {
    // generate HTML
    $loader = new TwigFilesystemLoader([
            EQ_BASEDIR.'/packages/realestate/views/_parts',
            EQ_BASEDIR.'/packages/realestate/views/governance'
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

    $template = $twig->load('AssemblyMandate.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();
