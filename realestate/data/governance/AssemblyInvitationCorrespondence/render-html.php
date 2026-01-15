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
use realestate\governance\AssemblyInvitationCorrespondence;
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
    'description'   => 'Generate an html view of a Mandate template.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific AssemblyInvitationCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\AssemblyInvitationCorrespondence',
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

$getFormattedDate = function($timestamp) {
    $tz = new DateTimeZone(constant('L10N_TIMEZONE'));
    $tz_offset = $tz->getOffset(new DateTime('@' . $timestamp));
    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    return date($date_format, $timestamp + $tz_offset);
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


$assemblyInvitationCorrespondence = AssemblyInvitationCorrespondence::id($params['id'])
    ->read([
        'assembly_id',
        'owner_id' => [
            'name',
            'firstname',
            'lastname',
            'address_street',
            'address_dispatch',
            'address_zip',
            'address_city',
            'address_country',
            'has_vat',
            'vat_number'
        ],
        'ownership_id'
    ])
    ->first(true);

if(!$assemblyInvitationCorrespondence) {
    throw new Exception('unknown_assembly_invitation', EQ_ERROR_UNKNOWN_OBJECT);
}


$assembly = Assembly::id($assemblyInvitationCorrespondence['assembly_id'])
    ->read([
        'name',
        'condo_id',
        'assembly_type',
        'assembly_date',
        'session_time_start',
        'assembly_invitation_date',
        'assembly_location',
        'heading_text_call',
        'closing_text_call',
        'assembly_items_ids' => [
            '@domain' => ['parent_group_id', 'is', null],
            'id',
            'order',
            'name',
            'is_group',
            'children_items_ids'
        ],
        'condo_id' => [
            'name', 'address', 'address_street', 'address_zip', 'address_city',
            'registration_number',
            'managing_agent_id' => [
                'identity_id'
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

$map_assembly_items = AssemblyItem::search(['assembly_id', '=', $assembly['id']])
    ->read([
        'name',
        'order',
        'description_call',
        'has_vote_required',
        'majority'
    ])
    ->get();


$lang = $params['lang'];

// retrieve template (subject & body)
$subject = '';
$introduction = '';

$template = Template::search([
        ['code', '=', 'general_meetings_call'],
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
            'firstname'         => $assemblyInvitationCorrespondence['owner_id']['firstname'],
            'lastname'          => $assemblyInvitationCorrespondence['owner_id']['lastname'],
            'condo'             => $assembly['condo_id']['name'],
            'assembly'          => $assembly['name'],
            'date'              => $getFormattedDate($assembly['assembly_date']),
            'location'          => $assembly['assembly_location'],
            'type'              => $map_types[$assembly['assembly_type']],
            'time_start'        => sprintf('%02d:%02d', $assembly['session_time_start'] / 3600, ($assembly['session_time_start'] % 3600) / 60)
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
}

$values = [
    'title'                     => $subject,
    'introduction'              => $introduction,

    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $organisation,
    'organisation_logo'         => $getOrganisationLogo($organisation['id']),

    'date'                      => $assembly['assembly_invitation_date'],
    'recipient'                 => $assemblyInvitationCorrespondence['owner_id'],

    'map_assembly_items'        => $map_assembly_items,

    // 'today_date'                => time(),
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

    $template = $twig->load('AssemblyInvitation.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();
