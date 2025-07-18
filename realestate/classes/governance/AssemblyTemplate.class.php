<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\governance;

class AssemblyTemplate extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'        => 'string',
                'description' => "Name of the assembly template.",
                'required'    => true
            ],

            'organization_id' => [
                'type'            => 'many2one',
                'description'     => "Organization managing the assembly.",
                'foreign_object'  => 'identity\Organisation',
                'required'        => true
            ],

            'assembly_item_templates_ids' => [
                'type'            => 'one2many',
                'description'     => "Templates used for this assembly.",
                'foreign_object'  => 'realestate\governance\AssemblyItemTemplate',
                'foreign_field'   => 'assembly_template_id'
            ]
        ];
    }
}
