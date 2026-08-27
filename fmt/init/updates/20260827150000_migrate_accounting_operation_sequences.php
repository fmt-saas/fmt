<?php

use fmt\setting\Setting;
use fmt\setting\SettingSequence;

$target_values = [];

$collect_sequence_values = static function(
    string $package,
    string $code_prefix,
    string $code_pattern,
    string $operation_suffix
) use (&$target_values): void {
    $settings = Setting::search([
            ['package', '=', $package],
            ['section', '=', 'accounting'],
            ['code', 'like', "{$code_prefix}%"]
        ])
        ->read(['code']);

    foreach($settings as $setting_id => $setting) {
        if(!preg_match($code_pattern, $setting['code'], $matches)) {
            continue;
        }

        $target_code = "operation.sequence.{$matches[1]}.{$matches[2]}.{$operation_suffix}";

        $sequences = SettingSequence::search([
                ['setting_id', '=', $setting_id],
                ['condo_id', '<>', null]
            ])
            ->read(['value', 'user_id', 'organisation_id', 'condo_id', 'ownership_id']);

        foreach($sequences as $sequence) {
            if(
                ($sequence['user_id'] ?? null) !== null
                || ($sequence['organisation_id'] ?? null) !== null
                || ($sequence['ownership_id'] ?? null) !== null
            ) {
                continue;
            }

            $condo_id = (int) $sequence['condo_id'];
            $sequence_value = max(1, (int) $sequence['value']);

            if(!isset($target_values[$condo_id][$target_code])) {
                $target_values[$condo_id][$target_code] = $sequence_value;
                continue;
            }

            $target_values[$condo_id][$target_code] = max(
                $target_values[$condo_id][$target_code],
                $sequence_value
            );
        }
    }
};

$collect_sequence_values(
    'sale',
    'invoice.sequence.',
    '/^invoice\.sequence\.([^.]+)\.([^.]+)$/',
    'SAL'
);

$collect_sequence_values(
    'purchase',
    'invoice.sequence.',
    '/^invoice\.sequence\.([^.]+)\.([^.]+)$/',
    'PUR'
);

$collect_sequence_values(
    'finance',
    'misc_operation.sequence.',
    '/^misc_operation\.sequence\.([^.]+)\.([^.]+)\.[^.]+$/',
    'MSC'
);

foreach($target_values as $condo_id => $sequences) {
    $selector = ['condo_id' => $condo_id];

    foreach($sequences as $code => $legacy_value) {
        Setting::assert_sequence('finance', 'accounting', $code, $legacy_value, $selector);

        $setting = Setting::search([
                ['package', '=', 'finance'],
                ['section', '=', 'accounting'],
                ['code', '=', $code]
            ])
            ->read(['id'])
            ->first();

        if(!$setting) {
            continue;
        }

        $sequence = SettingSequence::search([
                ['setting_id', '=', $setting['id']],
                ['user_id', 'is', null],
                ['organisation_id', 'is', null],
                ['condo_id', '=', $condo_id],
                ['ownership_id', 'is', null]
            ])
            ->read(['value'])
            ->first();

        $sequence_value = max($legacy_value, (int) ($sequence['value'] ?? 1));
        Setting::set_sequence('finance', 'accounting', $code, $sequence_value, $selector);
    }
}
