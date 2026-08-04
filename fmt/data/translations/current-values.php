<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => "Returns a given object currently stored translations depending on the linked condominium languages configuration.",
    'params' => [
        'id' => [
            'type'              => 'integer',
            'description'       => "The id of the concerned object.",
            'required'          => true
        ],
        'entity' => [
            'type'              => 'string',
            'description'       => "Name of the entity we want the translations for.",
            'required'          => true
        ],
        'field' => [
            'type'              => 'string',
            'description'       => "Optional parameter, the field we want the translations for.",
            'help'              => "If empty all entity multilang fields returned."
        ],
        'lang' => [
            'type'              => 'string',
            'description'       => "Optional parameter, the language for which we want the translations.",
            'help'              => "If empty all condominium languages returned."
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
if(!isset($params['field'])) {
    foreach($schema as $field => $conf) {
        if($conf['multilang'] ?? false) {
            $multilang_fields[] = $field;
        }
    }
}
else {
    $multilang_fields = [$params['field']];
}

$object = $entity::id($params['id'])
    ->read(['condo_id' => ['condo_langs_ids' => ['code']]])
    ->first(true);

if(!$object) {
    throw new Exception("unknown_object", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$object['condo_id']) {
    throw new Exception("object_not_linked_to_condo", EQ_ERROR_INVALID_PARAM);
}

$result = [];
foreach($object['condo_id']['condo_langs_ids'] as $condo_lang) {
    if(isset($params['lang']) && $params['lang'] !== $condo_lang['code']) {
        continue;
    }

    $translated_object = $entity::id($params['id'])
        ->read($multilang_fields, $condo_lang['code'])
        ->first(true);

    foreach($multilang_fields as $multilang_field) {
        $result[$condo_lang['code']][$multilang_field] = $translated_object[$multilang_field];
    }
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
