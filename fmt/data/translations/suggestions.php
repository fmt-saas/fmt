<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

[$params, $providers] = eQual::announce([
    'description'   => "Returns suggestions for missing translations of a given object.",
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
    Get current values of the multilang fields for all languages
*/

$current_values_params = [
    'id'        => $params['id'],
    'entity'    => $params['entity']
];
if(isset($params['field'])) {
    $current_values_params['field'] = $params['field'];
}

$map_langs_current_values = eQual::run('get', 'fmt_translations_current-values', $current_values_params);

// separate source current values from other languages
$source_lang_values = $map_langs_current_values[$params['source_lang']];
unset($map_langs_current_values[$params['source_lang']]);


/*
    Create response
*/

// request a Google Cloud token
$token_data = eQual::run('get', 'fmt_translations_google_refresh-token');
$token = $token_data['token'];

$result = [];
foreach($map_langs_current_values as $lang => $current_values) {
    if(isset($params['target_lang']) && $params['target_lang'] !== $lang) {
        continue;
    }

    $missing_values = [];
    foreach($source_lang_values as $field => $source_value) {
        $stripped_current_value = strip_tags($current_values[$field]);

        if(empty($stripped_current_value) && preg_match("/[a-z]/i", $source_value)) {
            $missing_values[$field] = $source_value;
        }
    }

    if(empty($missing_values)) {
        continue;
    }

    $data = eQual::run('get', 'fmt_translations_google_translate', [
        'token'         => $token,
        'contents'      => array_values($missing_values),
        'source_lang'   => $params['source_lang'],
        'target_lang'   => $lang
    ]);

    $result[$lang] = [];
    foreach($data['translations'] as $index => $translation_res) {
        $field = array_keys($missing_values)[$index];

        $result[$lang][$field] = $translation_res['translatedText'];
    }
}

$context
    ->httpResponse()
    ->body($result)
    ->send();
