<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => "Automatically translates the missing multilang fields of a given object.",
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
            'help'              => "If empty all entity multilang fields handled."
        ],
        'source_lang' => [
            'type'              => 'string',
            'description'       => "The language from which we want the translations.",
            'default'           => constant('DEFAULT_LANG')
        ],
        'target_lang' => [
            'type'              => 'string',
            'description'       => "Optional parameter, the language for which we want the translation.",
            'help'              => "If empty all condominium languages are handled."
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

$object = $entity::id($params['id'])->first();

if(!$object) {
    throw new Exception("unknown_object", EQ_ERROR_UNKNOWN_OBJECT);
}


/*
    Get translation suggestions
*/

$suggestions_params = [
    'id'            => $params['id'],
    'entity'        => $params['entity'],
    'source_lang'   => $params['source_lang']
];
if(isset($params['field'])) {
    $suggestions_params['field'] = $params['field'];
}
if(isset($params['target_lang'])) {
    $suggestions_params['target_lang'] = $params['target_lang'];
}

$suggestions_data = eQual::run('get', 'fmt_translations_suggestions', $suggestions_params);

if(!is_array($suggestions_data)) {
    trigger_error("APP::Invalid translation suggestions response: " . json_encode($suggestions_data), EQ_REPORT_ERROR);
    throw new Exception("invalid_translation_suggestions_response", EQ_ERROR_UNKNOWN);
}


/*
    Apply translation suggestions
*/

foreach($suggestions_data as $lang => $values) {
    if($lang === $params['source_lang']) {
        // should not happen but skip to be sure
        continue;
    }

    if(!is_array($values)) {
        trigger_error("APP::Invalid translation suggestions values for language $lang: " . json_encode($values), EQ_REPORT_ERROR);
        throw new Exception("invalid_translation_suggestions_response", EQ_ERROR_UNKNOWN);
    }

    $entity::id($params['id'])->update($values, $lang);
}


$context
    ->httpResponse()
    ->send();
