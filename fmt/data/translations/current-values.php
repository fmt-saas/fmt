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
            'help'              => "If empty all entity's multilang fields are returned."
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

/*
    Check given parameters
*/

$translations_params = [
    'id'        => $params['id'],
    'entity'    => $params['entity']
];

if(isset($params['field']) && $params['field'] !== '') {
    $translations_params['field'] = $params['field'];
}

$translations = eQual::run('get', 'core_model_translations', $translations_params);

$entity = $orm->getModel($params['entity']);
if(!$entity) {
    throw new Exception("unknown_entity", EQ_ERROR_INVALID_PARAM);
}

$object = $entity::id($params['id'])
    ->read(['condo_id' => ['condo_langs_ids' => ['code']]])
    ->first();

if(!$object) {
    throw new Exception("unknown_object", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$object['condo_id']) {
    throw new Exception("object_not_linked_to_condo", EQ_ERROR_INVALID_PARAM);
}


/*
    Create response
*/

$result = [];
foreach($object['condo_id']['condo_langs_ids'] as $condoLang) {
    $lang = $condoLang['code'];

    if(isset($params['lang']) && $params['lang'] !== '' && $params['lang'] !== $lang) {
        continue;
    }

    if(isset($translations[$lang])) {
        $result[$lang] = $translations[$lang];
    }
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
