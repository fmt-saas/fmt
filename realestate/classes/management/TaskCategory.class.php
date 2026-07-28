<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\management;

class TaskCategory extends \equal\orm\Model {

    public static function getName() {
        return 'Task Category';
    }

    public static function getDescription() {
        return 'Task categories identify management areas and define their visibility by employee role.';
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Short label to identify the task category.',
                'required'          => true,
                'multilang'         => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code used to identify the category.',
                'required'          => true,
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Scope and intended usage of the category.',
                'multilang'         => true
            ],

            'mailbox_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\email\Mailbox',
                'description'       => 'Mailbox associated with the category, if any.'
            ],

            'visibility_roles_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\management\TaskCategoryRole',
                'foreign_field'     => 'category_id',
                'description'       => 'Roles allowed to view this category.',
                'ondetach'          => 'delete'
            ]
        ];
    }
}