<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace fmt\setting;

class Setting extends \core\setting\Setting {


    public static function getColumns(): array {
        return [

            'setting_values_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\setting\SettingValue',
                'foreign_field'     => 'setting_id',
                'sort'              => 'asc',
                'order'             => 'name',
                'description'       => 'List of values related to the setting.'
            ],

            'setting_sequences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\setting\SettingSequence',
                'foreign_field'     => 'setting_id',
                'sort'              => 'asc',
                'order'             => 'name',
                'description'       => 'List of sequences related to the setting.'
            ]

        ];
    }

    protected static function getSelectorKeys(): array {
        return ['user_id', 'organisation_id', 'condo_id', 'ownership_id'];
    }

    protected static function getSettingValueClass(): string {
        return SettingValue::class;
    }

    protected static function getSettingSequenceClass(): string {
        return SettingSequence::class;
    }

    public static function get_value(string $package, string $section, string $code, $default = null, array $selectors = [], string $lang = null) {
        if(empty($selectors) || !array_is_list($selectors)) {
            // Handle a single associative selector.
            $selectors = [$selectors];
        }

        $setting_value = null;
        foreach($selectors as $selector) {
            $set_value = parent::get_value($package, $section, $code, null, $selector, $lang);
            if(!is_null($set_value)) {
                $setting_value = $set_value;
                break;
            }
        }

        return $setting_value ?? $default;
    }
}
