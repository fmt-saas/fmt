<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace communication\template;

use equal\orm\Model;

class TemplatePart extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Code of the template part.",
                'required'          => true
            ],

            'value' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Template body (html).",
                'multilang'         => true
            ],

            'template_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\template\Template',
                'description'       => "The template the part belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ]

        ];
    }
}
