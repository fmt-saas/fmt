<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class UpdateRequestLine extends Model {

    public static function getColumns() {
        return [

            'update_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\sync\UpdateRequest',
                'description'       => 'Reference to the parent update request.',
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['object_class', 'is_new']
            ],

            'object_class' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['update_request_id' => 'object_class'],
                'description'       => 'Field name of the targeted object.',
                'store'             => true
            ],

            'object_field' => [
                'type'              => 'string',
                'description'       => 'Field name of the targeted object.',
                'required'          => true
            ],

            'is_new' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'relation'          => ['update_request_line_id' => 'is_new'],
                'store'             => true,
                'description'       => 'JSON encoded new proposed value for the field.',
                'default'           => false
            ],

            'new_value' => [
                'type'              => 'string',
                'description'       => 'JSON encoded new proposed value for the field.'
            ],

            'old_value' => [
                'type'              => 'string',
                'description'       => 'Old JSON value (currently stored).',
                'visible'           => ['is_new', '=', false]
            ]

        ];
    }

}
