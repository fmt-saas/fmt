<?php

use fmt\setting\Setting;

$legacy_settings_ids = [];

$legacy_setting_patterns = [
    [
        'package' => 'sale',
        'prefix'  => 'invoice.sequence.',
        'pattern' => '/^invoice\.sequence\.[^.]+\.[^.]+$/'
    ],
    [
        'package' => 'purchase',
        'prefix'  => 'invoice.sequence.',
        'pattern' => '/^invoice\.sequence\.[^.]+\.[^.]+$/'
    ],
    [
        'package' => 'finance',
        'prefix'  => 'misc_operation.sequence.',
        'pattern' => '/^misc_operation\.sequence\.[^.]+\.[^.]+\.[^.]+$/'
    ]
];

foreach($legacy_setting_patterns as $legacy_setting_pattern) {
    $settings = Setting::search([
            ['package', '=', $legacy_setting_pattern['package']],
            ['section', '=', 'accounting'],
            ['code', 'like', "{$legacy_setting_pattern['prefix']}%"]
        ])
        ->read(['code']);

    foreach($settings as $setting_id => $setting) {
        if(preg_match($legacy_setting_pattern['pattern'], $setting['code'])) {
            $legacy_settings_ids[] = $setting_id;
        }
    }
}

if(count($legacy_settings_ids)) {
    // The default ORM delete is soft and cascades to the related sequence values.
    Setting::ids($legacy_settings_ids)->delete(false);
}
