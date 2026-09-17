<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use fmt\setting\Setting;
use documents\DocumentSignature;
use identity\Identity;
use identity\Organisation;
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

        'full' => [
            'description'       => 'Flag for requesting the "full" / empty version of the list.',
            'help'              => 'If set to true, the register will include all attendees without consideration of their representation or signature status.',
            'type'              => 'boolean',
            'default'           => false
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

$timezone_name = Setting::get_value('core', 'locale', 'time.zone', constant('L10N_TIMEZONE'));

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

$getFormattedTime = function($time_offset, $date_timestamp) use ($timezone_name) {
    if(!is_numeric($time_offset) || !is_numeric($date_timestamp)) {
        return '';
    }

    $time_offset = (int) $time_offset;
    if($time_offset < 0 || $time_offset >= 86400) {
        return '';
    }

    try {
        // A date is stored as UTC midnight, while a time is an offset from midnight in the configured timezone.
        $date = gmdate('Y-m-d', (int) $date_timestamp);
        $hours = intdiv($time_offset, 3600);
        $minutes = intdiv($time_offset % 3600, 60);
        $seconds = $time_offset % 60;

        $date_time = new DateTimeImmutable(
            sprintf('%s %02d:%02d:%02d', $date, $hours, $minutes, $seconds),
            new DateTimeZone($timezone_name)
        );

        return $date_time->format('H:i');
    }
    catch(\Throwable $e) {
        return '';
    }
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

$getRecipient = function($identity_id, $lang) {
    $identity = Identity::id($identity_id)
        ->read([
                'firstname', 'lastname', 'title', 'address_street', 'address_dispatch', 'address_zip',
                'address_city', 'address_country', 'has_vat', 'vat_number',
            ], $lang)
        ->first();

    $data = eQual::run('get', 'core_config_i18n', ['entity' => 'identity\Identity', 'lang' => $lang]);

    $title = $data['model']['title']['selection'][$identity['title']] ?? $identity['title'];

    return [
            'name'              => $title . ' ' . ucfirst($identity['firstname']) . ' ' . strtoupper($identity['lastname']),
            'address_street'    => $identity['address_street'],
            'address_dispatch'  => $identity['address_dispatch'],
            'address_zip'       => $identity['address_zip'],
            'address_city'      => $identity['address_city'],
            'address_country'   => $identity['address_country'],
            'has_vat'           => $identity['has_vat'],
            'vat_number'        => $identity['vat_number'],
    ];
};

$assembly = Assembly::id($params['id'])
    ->read([
        'name',
        'assembly_type',
        'assembly_date',
        'session_time_start',
        'assembly_location',
        'register_document_id',
        'count_owners',
        'count_shares',
        'count_represented_shares',
        'assembly_attendees_ids' => [
            '@domain' => ['is_valid', '=', true],
            'name',
            'register_document_signature_id' => ['sig_method', 'sig_drawn', 'sig_hash', 'sig_algo', 'sig_timestamp']
        ],
        'assembly_representations_ids' => ['attendee_id', 'ownership_id', 'representation_type'],
        'ownerships_ids' => ['id', 'name', 'address_recipient'],
        'condo_id' => [
            'name', 'address_street', 'address_city', 'address_zip', 'address_city',
            'registration_number',
            'lang_id' => ['code'],
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
            'ownership_id' => ['id', 'name', 'ownership_type', 'address_recipient', 'representative_owner_id' => ['name']],
            'property_lot_id' => ['name', 'code', 'is_primary']
        ]);

$map_ownerships_lots = [];
foreach($propertyLotOwnerships as $propertyLotOwnership) {
    if((!$propertyLotOwnership['date_to'] || $propertyLotOwnership['date_to'] > $assembly['assembly_date']) && $propertyLotOwnership['property_lot_id']['is_primary']) {
        if(!isset($map_ownerships_lots[$propertyLotOwnership['ownership_id']['id']])) {
            $map_ownerships_lots[$propertyLotOwnership['ownership_id']['id']] = [
                'name'              => $propertyLotOwnership['ownership_id']['name'],
                'address_recipient' => $propertyLotOwnership['ownership_id']['address_recipient'],
                'lots'              => [],
                'shares'            => 0.0,
                'type'              => $propertyLotOwnership['ownership_id']['ownership_type']
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


$owner_lang = $params['lang'];
$condo_lang = $assembly['condo_id']['lang_id']['code'] ?? $params['lang'];

$subject = 'Liste des présences';
$introduction = '';
$conclusion = '';


$ownerTemplate = Template::search([
        ['code', '=', 'general_meetings_register'],
        ['type', '=', 'document']
    ])
    ->read(['id', 'parts_ids' => ['name', 'value']], $owner_lang)
    ->first(true);

$condoTemplate = Template::id($ownerTemplate['id'])
    ->read(['id', 'parts_ids' => ['name', 'value']], $condo_lang)
    ->first(true);

$map_condo_template_parts = [];
foreach($condoTemplate['parts_ids'] as $part) {
    $map_condo_template_parts[$part['name']] = $part['value'];
}

$map_types = [
    'statutory' => [
        'fr' => 'Assemblée Générale Statutaire',
        'nl' => 'Statutaire Algemene Vergadering',
        'en' => 'Statutory General Meeting'
    ],
    'takeover' => [
        'fr' => 'Assemblée Générale de Reprise de gestion',
        'nl' => 'Algemene Vergadering voor de overname van het beheer',
        'en' => 'General Meeting for the Takeover of Management'
    ],
    'extraordinary' => [
        'fr' => 'Assemblée Générale Extraordinaire',
        'nl' => 'Buitengewone Algemene Vergadering',
        'en' => 'Extraordinary General Meeting'
    ],
    'constitutive' => [
        'fr' => 'Assemblée Générale Constitutive',
        'nl' => 'Constituerende Algemene Vergadering',
        'en' => 'Constitutive General Meeting'
    ]
];

foreach($ownerTemplate['parts_ids'] as $part) {
    $part_value = $part['value'];
    $part_lang = $owner_lang;
    if(is_null($part_value)) {
        $part_value = $map_condo_template_parts[$part['name']] ?? '';
        $part_lang = $condo_lang;
    }

    $type_label = $map_types[$assembly['assembly_type']][$part_lang]
        ?? $map_types[$assembly['assembly_type']][$condo_lang]
        ?? $map_types[$assembly['assembly_type']]['fr']
        ?? '';

    if($part['name'] == 'subject') {

        $subject = strip_tags($part_value);

        $map_values = [
            'condo'             => $assembly['condo_id']['name'],
            'assembly'          => $assembly['name'],
            'type'              => $type_label,
            'date'              => $getFormattedDate($assembly['assembly_date'])
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $subject = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $subject);

        $subject = strip_tags($subject);
    }
    elseif($part['name'] == 'introduction') {
        $introduction = $part_value;

        $map_values = [
            'condo'             => $assembly['condo_id']['name'],
            'assembly'          => $assembly['name'],
            'date'              => $getFormattedDate($assembly['assembly_date']),
            'location'          => $assembly['assembly_location'],
            'type'              => $type_label,
            'time_start'        => $getFormattedTime($assembly['session_time_start'], $assembly['assembly_date'])
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
    elseif($part['name'] == 'certification_full') {
        $certification_full = $part_value;

        $map_values = [
            'count_owners' => $assembly['count_owners'],
            'count_shares' => $assembly['count_shares'],
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $certification_full = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $certification_full);
    }
    elseif($part['name'] == 'certification_signed') {
        $certification_signed = $part_value;

        $map_values = [
            'count_representations'     => count($map_ownership_representations),
            'count_owners'              => $assembly['count_owners'],
            'count_represented_shares'  => $assembly['count_represented_shares'],
            'count_shares'              => $assembly['count_shares']
        ];
    }
}

$labels = $getLabels(
    $owner_lang,
    sprintf('%s/packages/realestate/i18n/%s/governance/%s.json', EQ_BASEDIR, $owner_lang, 'Assembly.'.$params['view_id'])
);

$values = [
    'title'                     => $subject,
    'introduction'              => $introduction,
    'conclusion'                => $conclusion,

    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $organisation,
    'organisation_logo'         => $getOrganisationLogo($organisation['id']),

    'full_register'             => $params['full'],
    'signed'                    => $params['signed'],

    'ownerships'                => $map_ownerships_lots,
    'attendees'                 => $map_attendees,
    'representations'           => $map_ownership_representations,

    'today_date'                => time(),
    'timezone'                  => $timezone_name,
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
