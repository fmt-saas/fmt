<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace fmt\setting;

class Setting extends \core\setting\Setting {


    public static function getColumns() {
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

    protected static function getSelectorKeys() {
        return ['user_id', 'organisation_id', 'condo_id'];
    }

    protected static function getSettingValueClass(): string {
        return SettingValue::class;
    }

    protected static function getSettingSequenceClass(): string {
        return SettingSequence::class;
    }

}
