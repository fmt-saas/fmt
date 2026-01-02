<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use core\setting\Setting;
use documents\DocumentSignature;
use realestate\governance\Assembly;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of the Attendance Register for a given Assembly.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'signed' => [
            'description'       => 'Flag for requesting the signed version of the register.',
            'type'              => 'boolean',
            'default'           => false
        ],

        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ],

        'view_id' => [
            'description' => 'View id of the template to use.',
            'type'        => 'string',
            'default'     => 'print.attendance_register'
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


$getLabels = function($lang) {
    return [
        'registration_number'            => Setting::get_value('sale', 'locale', 'label_registration-number', 'Registration n°', [], $lang),
        'vat_number'                     => Setting::get_value('sale', 'locale', 'label_vat-number', 'VAT n°', [], $lang),
        'number'                         => Setting::get_value('sale', 'locale', 'label_number', 'N°', [], $lang),
        'date'                           => Setting::get_value('sale', 'locale', 'label_date', 'Date', [], $lang),
        'status'                         => Setting::get_value('sale', 'locale', 'label_status', 'Status', [], $lang),
        'communication'                  => Setting::get_value('sale', 'locale', 'label_communication', 'Communication', [], $lang),
        'footer' => [
            'registration_number'        => Setting::get_value('sale', 'locale', 'label_footer-registration-number', 'Registration number', [], $lang),
            'iban'                       => Setting::get_value('sale', 'locale', 'label_footer-iban', 'IBAN', [], $lang),
            'email'                      => Setting::get_value('sale', 'locale', 'label_footer-email', 'Email', [], $lang),
            'web'                        => Setting::get_value('sale', 'locale', 'label_footer-web', 'Web', [], $lang),
            'tel'                        => Setting::get_value('sale', 'locale', 'label_footer-tel', 'Tel', [], $lang)
        ]
    ];
};


$assembly = Assembly::id($params['id'])
    ->read([
        'name',
        'condo_id',
        'assembly_type',
        'assembly_date',
        'assembly_location',
        'register_document_id',
        'count_shares',
        'count_represented_shares',
        'assembly_attendees_ids' => [
            '@domain' => ['is_valid', '=', true],
            'name',
            'register_document_signature_id' => ['sig_method', 'sig_drawn', 'sig_hash', 'sig_algo', 'sig_timestamp']
        ],
        'assembly_representations_ids' => ['attendee_id', 'ownership_id', 'representation_type'],
        'ownerships_ids' => ['id', 'name'],
        'condo_id' => [
            'name', 'address_street', 'address_city', 'address_zip', 'address_city',
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

if($params['signed'] && !$assembly['register_document_id']) {
    throw new Exception('missing_original_document', EQ_ERROR_INVALID_PARAM);
}

// retrieve owners, lots and shares
$property_lots_ids = [];

$propertyLotOwnerships = PropertyLotOwnership::search([
        ['condo_id', '=', $assembly['condo_id']['id']]
    ])
    ->read([
            'date_to',
            'ownership_id' => ['id', 'name', 'ownership_type', 'representative_owner_id' => ['name']],
            'property_lot_id' => ['name', 'code', 'is_primary']
        ]);

foreach($propertyLotOwnerships as $propertyLotOwnership) {
    if((!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assembly['assembly_date']) && $propertyLotOwnership['property_lot_id']['is_primary']) {
        if(!isset($map_ownerships_lots[$propertyLotOwnership['ownership_id']['id']])) {
            $map_ownerships_lots[$propertyLotOwnership['ownership_id']['id']] = [
                'name'      => $propertyLotOwnership['ownership_id']['name'],
                'lots'      => [],
                'shares'    => 0.0,
                'type'      => $propertyLotOwnership['ownership_id']['ownership_type']
            ];
            if($propertyLotOwnership['ownership_id']['ownership_type'] === 'joint') {
                $map_ownerships_lots[$propertyLotOwnership['ownership_id']['id']]['representative'] = $propertyLotOwnership['ownership_id']['representative_owner_id']['name'];
            }
        }
        $map_ownerships_lots[$propertyLotOwnership['ownership_id']['id']]['lots'][] = [
            'id'        => $propertyLotOwnership['property_lot_id']['id'],
            'name'      => $propertyLotOwnership['property_lot_id']['code'],
        ];
        $property_lots_ids[] = $propertyLotOwnership['property_lot_id']['id'];
    }
}

$apportionment = Apportionment::search([['condo_id', '=', $assembly['condo_id']['id']], ['is_statutory', '=', true]])->first();
$apportionmentShares = PropertyLotApportionmentShare::search([
        ['apportionment_id', '=', $apportionment['id']],
        ['property_lot_id', 'in', $property_lots_ids],
    ])
    ->read(['property_lot_id', 'property_lot_shares']);
$map_lots_shares = [];

foreach($apportionmentShares as $apportionmentShare) {
    $map_lots_shares[$apportionmentShare['property_lot_id']] = $apportionmentShare['property_lot_shares'];
}

foreach($map_ownerships_lots as $ownership_id => $ownership) {
    foreach($ownership['lots'] as $lot) {
        $map_ownerships_lots[$ownership_id]['shares'] += $map_lots_shares[$lot['id']];
    }
}



// retrieve signatures from the attendance register
// #memo - for original document, we don't handle the signatures (document not signed yet)

$map_attendees = [];
$map_ownership_representations = [];

if($params['signed']) {
    foreach($assembly['assembly_attendees_ids'] as $assemblyAttendee) {
        if($assemblyAttendee['register_document_signature_id'] && $assemblyAttendee['register_document_signature_id']['sig_method'] == 'ses') {
            $assemblyAttendee['register_document_signature_id']['sig_drawn'] = base64_encode($assemblyAttendee['register_document_signature_id']['sig_drawn']);
        }
        $map_attendees[$assemblyAttendee['id']] = $assemblyAttendee;
    }
    foreach($assembly['assembly_representations_ids'] as $assemblyRepresentation) {
        $map_ownership_representations[$assemblyRepresentation['ownership_id']] = $assemblyRepresentation;
    }
}


$lang = $params['lang'];


$values = [
    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $assembly['condo_id']['managing_agent_id'],
    'organisation_logo'         => $getOrganisationLogo($assembly['condo_id']['managing_agent_id']['id'], 'realestate\management\ManagingAgent'),

    'signed'                    => $params['signed'],

    'ownerships'                => $map_ownerships_lots,
    'attendees'                 => $map_attendees,
    'representations'           => $map_ownership_representations,

    'today_date'                => time(),
    'timezone'                  => constant('L10N_TIMEZONE'),
    'locale'                    => constant('L10N_LOCALE'),
    'date_format'               => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),

    'labels'                    => $getLabels($lang),
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
            new TwigFilter('format_money', function ($value) {
                return number_format((float) $value, 2, ",", ".").' €';
            })
        );

    $template = $twig->load('Assembly.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();
