<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use core\Translation;

[$params, $providers] = eQual::announce([
    'description'   => "Returns a given object translations depending on the linked condominium languages configurations.",
    'params' => [
        'id' => [
            'type'              => 'integer',
            'description'       => "The id of the concerned object",
            'required'          => true
        ],
        'entity' => [
            'type'              => 'string',
            'description'       => "Name of the entity we want the translations for.",
            'required'          => true
        ],
        'field' => [
            'type'              => 'string',
            'description'       => "Optional parameter, the field we want the translations for."
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

$entity = $orm->getModel($params['entity']);
if(!$entity) {
    throw new Exception("unknown_entity", EQ_ERROR_INVALID_PARAM);
}

$schema = $entity->getSchema();
if(isset($params['field'])) {
    if(!isset($schema[$params['field']])) {
        throw new Exception("unknown_field", EQ_ERROR_INVALID_PARAM);
    }
    if(!isset($schema[$params['field']]['multilang']) || !$schema[$params['field']]['multilang']) {
        throw new Exception("not_multilang_field", EQ_ERROR_INVALID_PARAM);
    }
}

$multilang_fields = [];
foreach($schema as $field => $conf) {
    if($conf['multilang'] ?? false) {
        $multilang_fields[] = $field;
    }
}

$object = $entity::id($params['id'])
    ->read(array_merge(['condo_id' => ['condo_langs_ids' => ['code', 'is_primary']]], $multilang_fields))
    ->first(true);

if(!$object) {
    throw new Exception("unknown_object", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$object['condo_id']) {
    throw new Exception("object_not_linked_to_condo", EQ_ERROR_INVALID_PARAM);
}

$result = [];

$translations = Translation::search([
    ['object_class', '=', $params['entity']],
    ['object_id', '=', $params['id']],
])
    ->read(['language', 'object_field', 'value'])
    ->get(true);

foreach($multilang_fields as $field) {
    if(!empty($params['field']) && $field !== $params['field']) {
        continue;
    }

    $field_result = [];
    foreach($object['condo_id']['condo_langs_ids'] as $condo_lang) {
        if($condo_lang['code'] === constant('DEFAULT_LANG')) {
            // handle default language (values from the object)
            $field_result[constant('DEFAULT_LANG')] = [
                'value'             => $object[$field],
                'possible_values'   => [],
            ];
        }
        else {
            // handle additional languages (values from translations objects)
            $translation_value = null;
            foreach($translations as $translation) {
                if($translation['language'] === $condo_lang['code'] && $translation['object_field'] === $field) {
                    $translation_value = $translation['value'];
                    break;
                }
            }

            $field_result[$condo_lang['code']] = [
                'value'             => $translation_value,
                'possible_values'   => []
            ];
        }
    }

    $result[$field] = $field_result;
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
