<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\management;

class ManagementProcess extends \equal\orm\Model {

    public static function getName() {
        return 'Management Process';
    }

    public static function getDescription() {
        return 'Management processes identify condominium management areas and define the employee roles assigned to them.';
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Short label to identify the management process.',
                'required'          => true,
                'multilang'         => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code used to identify the management process.',
                'required'          => true,
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Scope and intended usage of the management process.',
                'multilang'         => true
            ],

            'mailbox_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\email\Mailbox',
                'description'       => 'Mailbox associated with the management process, if any.'
            ],

            'roles_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'hr\role\Role',
                'foreign_field'     => 'management_processes_ids',
                'rel_table'         => 'realestate_management_managementprocess_rel_role',
                'rel_foreign_key'   => 'role_id',
                'rel_local_key'     => 'management_process_id',
                'description'       => 'Roles assigned to this management process.'
            ]
        ];
    }
}