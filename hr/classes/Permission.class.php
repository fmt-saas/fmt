<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
