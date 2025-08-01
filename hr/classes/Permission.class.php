<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr;

class Permission extends \core\Permission {

    public function getTable() {
        return 'hr_permission';
    }

    public static function getColumns() {
        return [

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'foreign_field'     => 'permissions_ids',
                'description'       => "Targeted role to which the permission applies.",
                'ondelete'          => 'cascade',
                'required'          => true
            ]

        ];
    }

}
