<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\template\Template;
use fmt\setting\Setting;
use documents\DocumentSignature;
use identity\Organisation;
use realestate\governance\Assembly;
use realestate\governance\AssemblyMinutesCorrespondence;
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
            'description'       => 'Identifier of the specific AssemblyMinutesCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\AssemblyMinutesCorrespondence',
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


$assemblyMinutesCorrespondence = AssemblyMinutesCorrespondence::id($params['id'])
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

if(!$assemblyMinutesCorrespondence) {
    throw new Exception('unknown_assembly_invitation', EQ_ERROR_UNKNOWN_OBJECT);
}


$assembly = Assembly::id($assemblyMinutesCorrespondence['assembly_id'])
    ->read([
        'name',
        'assembly_type',
        'assembly_date',
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
$subject = 'Compte rendu';
$introduction = '';

$template = Template::search([
        ['code', '=', 'general_meetings_minutes_correspondence'],
        ['type', '=', 'document']
    ])
    ->read( ['id','parts_ids' => ['name', 'value']])
    ->first(true);

foreach($template['parts_ids'] as $part_id => $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);

        // #todo #translation
        $map_types = [
            'statutory' => 'Assemblée Générale Statutaire',
            'takeover' => 'Assemblée Générale de Reprise de gestion',
            'extraordinary' => 'Assemblée Générale Extraordinaire',
            'constitutive' => 'Assemblée Générale Constitutive'
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

        $map_values = [
            'firstname'         => $assemblyMinutesCorrespondence['owner_id']['firstname'],
            'lastname'          => $assemblyMinutesCorrespondence['owner_id']['lastname'],
            'condo'             => $assembly['condo_id']['name'],
            'date'              => $getFormattedDate($assembly['assembly_date'])
        ];

        // Replace {var} items with corresponding values, set in $map_values
        $introduction = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($map_values) {
            $key = $matches[1];
            return $map_values[$key] ?? '';
        }, $introduction);
    }
}

$labels = $getLabels($lang, sprintf('%s/packages/realestate/i18n/%s/governance/%s.json', EQ_BASEDIR, $lang, 'AssemblyMinutes.'.$params['view_id']));

$values = [
    'title'                     => $subject,
    'introduction'              => $introduction,

    'assembly'                  => $assembly,
    'condominium'               => $assembly['condo_id'],

    'organisation'              => $organisation,
    'organisation_logo'         => $getOrganisationLogo($organisation['id']),

    'date'                      => $assembly['assembly_invitation_date'],
    'recipient'                 => $assemblyMinutesCorrespondence['owner_id'],

    'map_assembly_items'        => $map_assembly_items,

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

    $template = $twig->load('AssemblyMinutes.'.$params['view_id'].'.html');
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->body($html)
    ->send();
