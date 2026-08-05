<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => "Automatically translates the missing multilang fields of a given object.",
    'params' => [
        'domain' => [
            'type'              => 'array',
            'description'       => "Criteria that results have to match (serie of conjunctions)",
            'default'           => []
        ],
        'language' => [
            'type'              => 'string',
            'description'       => "Filter the target language to display.",
            'selection'         => [
                'all',
                'en',
                'fr',
                'nl'
            ],
            'default'           => 'all'
        ],

        // Fields
        'entity' => [
            'type'              => 'string',
            'description'       => "Entity of the object for which the translation is missing."
        ],
        'object_id' => [
            'type'              => 'string',
            'description'       => "Identifier of the object for which the translation is missing."
        ],
        'field' => [
            'type'              => 'string',
            'description'       => 'Name of the field that needs to be translated.'
        ],
        'original_lang' => [
            'type'              => 'string',
            'description'       => "Language of the base value the translation should be based on.",
            'default'           => constant('DEFAULT_LANG')
        ],
        'original_value' => [
            'type'              => 'string',
            'description'       => "The value to translate."
        ],
        'target_lang' => [
            'type'              => 'string',
            'description'       => "The targeted language the original value must be translated in."
        ],
        'target_value' => [
            'type'              => 'string',
            'description'       => "The translated value."
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'UTF-8',
        'accept-origin' => '*'
    ],
    'constants'     => ['DEFAULT_LANG'],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$domain = new Domain($params['domain']);

$result = [];
foreach($domain->getClauses() as $clause) {
    $condo_id = null;
    $object_class = null;
    $sub_domain = [];
    foreach($clause->getConditions() as $condition) {
        if($condition->getOperand() === 'condo_id') {
            $condo_id = $condition->getValue();
        }
        elseif($condition->getOperand() === 'entity') {
            $object_class = $condition->getValue();
        }
        else {
            $sub_domain[] = $condition->toArray();
        }
    }

    if(!$condo_id) {
        throw new Exception("missing_condo_id", EQ_ERROR_MISSING_PARAM);
    }
    if(!$object_class) {
        throw new Exception("missing_entity", EQ_ERROR_MISSING_PARAM);
    }

    $condo = Condominium::id($condo_id)
        ->read(['condo_langs_ids' => ['code']])
        ->first();

    $entity = $orm->getModel($object_class);
    if(!$entity) {
        throw new Exception("unknown_entity", EQ_ERROR_INVALID_PARAM);
    }

    $multilang_fields = [];
    foreach($entity->getSchema() as $field => $conf) {
        if($conf['multilang'] ?? false) {
            $multilang_fields[] = $field;
        }
    }

    $base_objects = [];
    foreach($condo['condo_langs_ids'] as $condo_lang) {
        if($condo_lang['code'] !== constant('DEFAULT_LANG')) {
            continue;
        }

        $base_objects = $entity::search($sub_domain)
            ->read($multilang_fields)
            ->get();
    }

    foreach($condo['condo_langs_ids'] as $condo_lang) {
        if($condo_lang['code'] === constant('DEFAULT_LANG')) {
            continue;
        }
        if($params['language'] !== 'all' && $params['language'] !== $condo_lang['code']) {
            continue;
        }

        $translated_objects = $entity::search($sub_domain)
            ->read($multilang_fields, $condo_lang['code'])
            ->get();

        foreach($translated_objects as $id => $translated_object) {
            $base_object = $base_objects[$id];

            foreach($multilang_fields as $multilang_field) {
                if(!empty($translated_object[$multilang_field])) {
                    // skip value already translated
                    continue;
                }
                if(empty($base_object[$multilang_field]) || !preg_match("/[a-z]/i", $base_object[$multilang_field])) {
                    // skip no value to translate
                    continue;
                }

                $result[] = [
                    'entity'            => $object_class,
                    'object_id'         => $id,
                    'field'             => $multilang_field,
                    'original_lang'     => constant('DEFAULT_LANG'),
                    'original_value'    => $base_object[$multilang_field],
                    'target_lang'       => $condo_lang['code'],
                    'target_value'      => ''
                ];
            }
        }
    }
}

$context
    ->httpResponse()
    ->header('X-Total-Count', count($result))
    ->body($result)
    ->send();
