<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use fmt\setting\Setting;
use identity\Identity;
use identity\Organisation;
use realestate\governance\Assembly;
use realestate\governance\AssemblyItem;
use Twig\TwigFilter;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a HTML preview of the Assembly invitation (final invites are generated through AssemblyInvite).',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
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


//  #todo - add support for a single ownership / owner_id , in the same way used for FundRequest

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
        'has_call_option_majority',
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
            'lang_id' => ['code'],
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


$owner_lang = $params['lang'];
$condo_lang = $assembly['condo_id']['lang_id']['code'] ?? $params['lang'];

// retrieve template (subject & body)
$subject = '';
$introduction = '';
$conclusion = '';
$legal_notes = '';

$ownerTemplate = Template::search([
        ['code', '=', 'general_meetings_agenda'],
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
            // 'firstname'         => $owner['identity_id']['firstname'],
            // 'lastname'          => $owner['identity_id']['lastname'],
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
    elseif($part['name'] == 'conclusion') {
        $conclusion = $part_value;
    }
    elseif($part['name'] == 'legal_notes') {
        $legal_notes = $part_value;
    }
}

$labels = $getLabels(
    $owner_lang,
    sprintf('%s/packages/realestate/i18n/%s/governance/%s.json', EQ_BASEDIR, $owner_lang, 'AssemblyAgenda.'.$params['view_id'])
);

$values = [
    'title'                     => $subject,
    'introduction'              => $introduction,
    'conclusion'                => $conclusion,
    'legal_notes'               => $legal_notes,

    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $organisation,
    'organisation_logo'         => $getOrganisationLogo($organisation['id']),

    'date'                      => $assembly['assembly_invitation_date'],

    'map_assembly_items'        => $map_assembly_items,

    // 'today_date'                => time(),
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

    $template = $twig->load('AssemblyAgenda.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();
