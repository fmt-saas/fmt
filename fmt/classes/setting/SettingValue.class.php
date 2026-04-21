<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\setting;

class SettingValue extends \core\setting\SettingValue {

    public static function getColumns() {
        return [

            'setting_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\setting\Setting',
                'description'       => 'Setting the value relates to.',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User the setting is specific to (optional).',
                'ondelete'          => 'cascade'
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'Organisation the setting is specific to (optional).',
                'ondelete'          => 'cascade'
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Condominium the setting is specific to (optional).',
                'ondelete'          => 'cascade'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\ownership\Ownership',
                'description'       => "Ownership the setting is specific to (optional).",
                'default'           => 0,
                'ondelete'          => 'cascade'
            ]

        ];
    }

    public function getUnique() {
        return [
            ['setting_id', 'user_id', 'organisation_id', 'condo_id', 'ownership_id']
        ];
    }

}
