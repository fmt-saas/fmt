<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use equal\orm\Domain;

[$params, $providers] = eQual::announce([
    'description'   => "Returns missing translations for the multilang fields of objects matching a domain.",
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
            'description'       => "Language of the base value the translation should be based on."
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
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$domain = new Domain($params['domain']);
$requested_original_lang = $params['original_lang'] ?? '';

$is_translatable_value = static function($value): bool {
    if(!is_string($value)) {
        return false;
    }

    return preg_match("/[a-z]/i", strip_tags($value)) === 1;
};

$is_empty_translation = static function($value): bool {
    if(!is_string($value)) {
        return empty($value);
    }

    return trim(strip_tags($value)) === '';
};

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

    $entity = $orm->getModel($object_class);
    if(!$entity) {
        throw new Exception("unknown_entity", EQ_ERROR_INVALID_PARAM);
    }

    $schema = $entity->getSchema();
    if(isset($schema['condo_id'])) {
        $sub_domain[] = ['condo_id', '=', $condo_id];
    }

    $object_ids = $entity::search($sub_domain)->ids();

    foreach($object_ids as $object_id) {
        $current_values = eQual::run('get', 'fmt_translations_current-values', [
            'id'        => $object_id,
            'entity'    => $object_class
        ]);

        if(!is_array($current_values) || !count($current_values)) {
            continue;
        }

        $original_lang = $requested_original_lang;
        if($original_lang === '' || !isset($current_values[$original_lang])) {
            $original_lang = array_key_first($current_values);
        }

        if(!$original_lang || !isset($current_values[$original_lang])) {
            continue;
        }

        $source_values = $current_values[$original_lang];

        foreach($current_values as $target_lang => $target_values) {
            if($target_lang === $original_lang) {
                continue;
            }
            if($params['language'] !== 'all' && $params['language'] !== $target_lang) {
                continue;
            }

            foreach($source_values as $field => $source_value) {
                if(!$is_empty_translation($target_values[$field] ?? null)) {
                    // skip value already translated
                    continue;
                }
                if(!$is_translatable_value($source_value)) {
                    // skip no value to translate
                    continue;
                }

                $result[] = [
                    'entity'            => $object_class,
                    'object_id'         => $object_id,
                    'field'             => $field,
                    'original_lang'     => $original_lang,
                    'original_value'    => $source_value,
                    'target_lang'       => $target_lang,
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
