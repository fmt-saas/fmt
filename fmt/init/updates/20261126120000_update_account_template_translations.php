<?php

use finance\accounting\AccountChartTemplate;
use finance\accounting\AccountTemplate;

$source_file = __DIR__ . '/../data/20-finance_accounting_AccountTemplate.json';

if(!is_file($source_file)) {
    throw new Exception('account_template_translation_source_missing', EQ_ERROR_INVALID_CONFIG);
}

$source_sets = json_decode(file_get_contents($source_file), true);

if(!is_array($source_sets)) {
    throw new Exception('invalid_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
}

$codes_by_source_id = [];
$descriptions_by_language = [];

foreach($source_sets as $source_set) {
    if(($source_set['name'] ?? null) !== 'finance\\accounting\\AccountTemplate') {
        continue;
    }

    $language = $source_set['lang'] ?? null;
    if(!is_string($language) || $language === '') {
        throw new Exception('invalid_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
    }

    $descriptions_by_language[$language] = [];

    foreach($source_set['data'] ?? [] as $source_account) {
        $source_id = (int) ($source_account['id'] ?? 0);
        if(!$source_id) {
            throw new Exception('invalid_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
        }

        if($language === 'en') {
            $code = $source_account['code'] ?? null;
            if(!is_string($code) || $code === '') {
                throw new Exception('invalid_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
            }

            $codes_by_source_id[$source_id] = $code;
        }

        $description = $source_account['description'] ?? null;
        if(!is_string($description) || $description === '') {
            throw new Exception('invalid_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
        }

        $descriptions_by_language[$language][$source_id] = $description;
    }
}

foreach($descriptions_by_language as $descriptions) {
    if(count($descriptions) !== count($codes_by_source_id)) {
        throw new Exception('incomplete_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
    }

    foreach($descriptions as $source_id => $description) {
        if(!isset($codes_by_source_id[$source_id])) {
            throw new Exception('incomplete_account_template_translation_source', EQ_ERROR_INVALID_CONFIG);
        }
    }
}

$account_chart_template = AccountChartTemplate::search([
        ['condo_id', '=', null]
    ])
    ->read([
        'accounts_ids' => ['code']
    ])
    ->first();

if(!$account_chart_template) {
    return;
}

$account_template_ids_by_code = [];

foreach($account_chart_template['accounts_ids'] as $account_template_id => $account_template) {
    $account_template_ids_by_code[$account_template['code']] = $account_template_id;
}

foreach($descriptions_by_language as $language => $descriptions) {
    foreach($descriptions as $source_id => $description) {
        $code = $codes_by_source_id[$source_id];
        if(!isset($account_template_ids_by_code[$code])) {
            continue;
        }

        AccountTemplate::id($account_template_ids_by_code[$code])
            ->update(['description' => $description], $language);
    }
}
