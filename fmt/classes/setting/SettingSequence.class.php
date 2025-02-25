<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace fmt\setting;

class SettingSequence extends \core\setting\SettingSequence {

    public static function getColumns() {
        return [

            'setting_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\setting\Setting',
                'description'       => 'Setting the value relates to.',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'Organisation the setting is specific to (optional).',
                'default'           => 1,
                'ondelete'          => 'cascade'
            ]

        ];
    }

}
