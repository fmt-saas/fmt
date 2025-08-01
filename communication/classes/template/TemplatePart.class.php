<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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
