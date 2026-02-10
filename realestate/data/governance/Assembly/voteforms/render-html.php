<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use fmt\setting\Setting;
use realestate\governance\Assembly;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate an html view of the vote forms for a given Assembly.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific Assembly to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'ownership_id' => [
            'description'       => 'Identifier of the specific Ownership to consider, if any.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership'
        ],

        'debug' => [
            'type'              => 'boolean',
            'default'           => false
        ],

        'view_id' => [
            'description'       => 'View id of the template to use.',
            'type'              => 'string',
            'default'           => 'print.voteforms'
        ],

        'lang' => [
            'description'       => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'              => 'string',
            'default'           => 'fr'
        ]
    ],
    'access' => [
        'visibility'    => 'protected'
    ],
    'response' => [
        'content-type'  => 'text/html',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context'],
    'constants' => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

/**
 * Methods
 */

$getFormattedDate = function($timestamp) {
    $tz = new DateTimeZone(constant('L10N_TIMEZONE'));
    $tz_offset = $tz->getOffset(new DateTime('@' . $timestamp));
    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    return date($date_format, $timestamp + $tz_offset);
};

$getFormattedTime = function($timestamp, $adapt = false) {
    if($adapt) {
        $tz = new \DateTimeZone(constant('L10N_TIMEZONE'));
        $tz_offset = $tz->getOffset(new \DateTime('@' . time()));
        $local_time = $timestamp + $tz_offset;
        $local_today = strtotime('today', $local_time);
        $timestamp = $local_time - $local_today;
    }
    return sprintf('%02d:%02d', $timestamp / 3600, ($timestamp % 3600) / 60);
};

/**
 * Action
 */

$assembly = Assembly::id($params['id'])
    ->read([
        'name',
        'assembly_date',
        'condo_id' => [
            'name'
        ],
        'ownerships_ids' => [
            'name'
        ],
        'assembly_items_ids' => [
            '@domain' => ['parent_group_id', 'is', null],
            'name',
            'order',
            'has_vote_required',
            'children_items_ids' => [
                '@domain' => ['has_vote_required', '=', true],
                'name',
                'order'
            ]
        ]
    ])
    ->first();

if(!$assembly) {
    throw new Exception('unknown_assembly', EQ_ERROR_UNKNOWN_OBJECT);
}

$ownerships = [];
foreach($assembly['ownerships_ids'] as $ownership) {
    if(isset($params['ownership_id']) && $ownership['id'] !== $params['ownership_id']) {
        continue;
    }

    $ownerships[] = [
        'name' => $ownership['name']
    ];
}
usort($ownerships, fn($a, $b) => strcmp($a['name'], $b['name']));

if(empty($ownerships)) {
    throw new Exception("unknown_ownership", EQ_ERROR_UNKNOWN_OBJECT);
}

$vote_items = [];
foreach($assembly['assembly_items_ids'] as $parent_item) {
    if(!$parent_item['has_vote_required'] && count($parent_item['children_items_ids']) === 0) {
        continue;
    }

    $vote_item = [
        'name'              => $parent_item['name'],
        'order'             => $parent_item['order'],
        'has_vote_required' => $parent_item['has_vote_required'],
        'children'          => []
    ];

    foreach($parent_item['children_items_ids'] as $child_item) {
        $vote_item['children'][] = [
            'name'  => $child_item['name'],
            'order' => $child_item['order']
        ];
    }

    $vote_items[] = $vote_item;
}
usort($vote_items, fn($a, $b) => $a['order'] <=> $b['order']);

$values = [
    'title'         => 'Formulaire vote',
    'assembly'      => $assembly,
    'condominium'   => $assembly['condo_id'],
    'ownerships'    => $ownerships,
    'vote_items'    => $vote_items,

    'today_date'    => time(),
    'timezone'      => constant('L10N_TIMEZONE'),
    'locale'        => constant('L10N_LOCALE'),
    'date_format'   => Setting::get_value('core', 'locale', 'date_format', 'm/d/Y'),

    'debug'         => $params['debug']
];

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/realestate/views/governance");
    $twig = new TwigEnvironment($loader);

    /** @var ExtensionInterface $extension **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

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
