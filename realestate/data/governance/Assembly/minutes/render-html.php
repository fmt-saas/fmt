<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use core\setting\Setting;
use documents\DocumentSignature;
use identity\Organisation;
use realestate\governance\Assembly;
use realestate\governance\AssemblyAttendee;
use realestate\governance\AssemblyItem;
use realestate\ownership\Ownership;
use realestate\property\Apportionment;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of the Minutes for a given Assembly.',
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
            'default'     => 'print.minutes'
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

$getFormattedDate = function($timestamp) {
    $tz = new DateTimeZone(constant('L10N_TIMEZONE'));
    $tz_offset = $tz->getOffset(new DateTime('@' . $timestamp));
    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    return date($date_format, $timestamp + $tz_offset);
};

$getFormattedTime = function($timestamp, $adapt=false) {
    if($adapt) {
        $tz = new \DateTimeZone(constant('L10N_TIMEZONE'));
        $tz_offset = $tz->getOffset(new \DateTime('@' . time()));
        $local_time = $timestamp + $tz_offset;
        $local_today = strtotime('today', $local_time);
        $timestamp = $local_time - $local_today;
    }
    return sprintf('%02d:%02d', $timestamp / 3600, ($timestamp % 3600) / 60);
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
        'session_time_start',
        'session_time_end',
        'assembly_location',
        'minutes_document_id',
        'heading_text_minutes',
        'closing_text_minutes',
        'ownerships_ids' => ['name'],
        'count_owners',
        'count_represented_owners',
        'count_shares',
        'count_represented_shares',
        'assembly_attendees_ids' => [
            '@domain' => [['is_valid', '=', true], ['has_signed_minutes', '=', true]],
            'name',
            'attendee_role',
            'has_signed_minutes',
            'minutes_document_signature_id' => ['sig_method', 'sig_drawn', 'sig_hash', 'sig_algo', 'sig_timestamp']
        ],
        'assembly_items_ids' => [
            '@domain' => ['parent_group_id', 'is', null],
            'id',
            'order',
            'name',
            'is_group',
            'children_items_ids'
        ],
        'condo_id' => [
            'name', 'address_street', 'address_city', 'address_zip', 'address_city',
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
    ->first();

if(!$assembly) {
    throw new Exception('unknown_ownership_transfer', EQ_ERROR_UNKNOWN_OBJECT);
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

if($params['signed'] && !$assembly['minutes_document_id']) {
    throw new Exception('missing_original_document', EQ_ERROR_INVALID_PARAM);
}

$map_ownerships = [];
foreach($assembly['ownerships_ids'] as $ownership_id => $ownership) {
    $map_ownerships[$ownership_id] = $ownership;
}

// list of attendees that signed the document
$map_attendees = [];

if($params['signed']) {
    foreach($assembly['assembly_attendees_ids'] as $assemblyAttendee) {
        if(!$assemblyAttendee['has_signed_minutes']) {
            continue;
        }
        if($assemblyAttendee['minutes_document_signature_id'] && $assemblyAttendee['minutes_document_signature_id']['sig_method'] == 'ses') {
            $assemblyAttendee['minutes_document_signature_id']['sig_drawn'] = base64_encode($assemblyAttendee['minutes_document_signature_id']['sig_drawn']);
        }
        $map_attendees[$assemblyAttendee['id']] = $assemblyAttendee;
    }
}

$map_assembly_items = AssemblyItem::search(['assembly_id', '=', $assembly['id']])
    ->read([
        'name',
        'order',
        'status',
        'description_minutes',
        'has_vote_required',
        'majority',
        'vote_result',
        'assembly_votes_ids' => [
            'vote_value',
            'vote_weight',
            'assembly_attendee_id',
            'ownership_id'
        ]
    ])
    ->get();

$lang = $params['lang'];


// retrieve template (subject & body)
$subject = '';
$introduction = '';
$conclusion = '';

$template = Template::search([
        ['code', '=', 'general_meetings_minutes'],
        ['type', '=', 'document']
    ])
    ->read( ['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        $map_types = [
            'statutory' => 'Assemblée Générale Statutaire',
            'takeover' => 'Assemblée Générale de Reprise de gestion',
            'ordinary' => 'Assemblée Générale Ordinaire',
            'extraordinary' => 'Assemblée Générale Extraordinaire'
        ];

        $map_values = [
            'condo'             => $assembly['condo_id']['name'],
            'assembly'          => $assembly['name'],
            'type'              => $map_types[$assembly['assembly_type']],
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
        $introduction = $part['value'];

        $map_types = [
            'statutory' => 'Assemblée Générale Statutaire',
            'takeover' => 'Assemblée Générale de Reprise de gestion',
            'ordinary' => 'Assemblée Générale Ordinaire',
            'extraordinary' => 'Assemblée Générale Extraordinaire',
            'constitutive' => 'Assemblée Générale Constitutive'
        ];

        $map_values = [
            // 'firstname'         => $owner['identity_id']['firstname'],
            // 'lastname'          => $owner['identity_id']['lastname'],
            'condo'             => $assembly['condo_id']['name'],
            'assembly'          => $assembly['name'],
            'date'              => $getFormattedDate($assembly['assembly_date']),
            'location'          => $assembly['assembly_location'],
            'condo_city'        => $assembly['condo_id']['address_city'],
            'type'              => $map_types[$assembly['assembly_type']],
            'time_start'        => $getFormattedTime($assembly['session_time_start']),
            'time_end'          => $getFormattedTime($assembly['session_time_end']),
            'count_owners'      => $assembly['count_owners'],
            'count_represented_owners'=> $assembly['count_represented_owners'],
            'count_shares'      => $assembly['count_shares'],
            'count_represented_shares'=> $assembly['count_represented_shares']
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
    elseif($part['name'] == 'conclusion') {
        $conclusion = $part['value'];

        $map_values = [
            'condo'             => $assembly['condo_id']['name'],
            'assembly'          => $assembly['name'],
            'date'              => $getFormattedDate($assembly['assembly_date']),
            'location'          => $assembly['assembly_location'],
            'condo_city'        => $assembly['condo_id']['address_city'],
            'type'              => $map_types[$assembly['assembly_type']],
            'time_end'          => $getFormattedTime($assembly['session_time_end'])
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $conclusion = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $conclusion);
    }
}

$values = [
    'title'                     => $subject,
    'introduction'              => $introduction,
    'conclusion'                => $conclusion,

    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $organisation,
    'organisation_logo'         => $getOrganisationLogo($organisation['id']),

    'signed'                    => $params['signed'],

    'map_ownerships'            => $map_ownerships,
    'map_attendees'             => $map_attendees,
    'map_assembly_items'        => $map_assembly_items,

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
