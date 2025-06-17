<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace tracking;

class CaseFile extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'folder_node_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\navigation\Node',
                'description'       => 'Specific folder holding all documents relating to the case, if any.',
                'domain'            => [['node_type', '=', 'folder'], ['condo_id', '=', 'object.condo_id']]
            ],

        ];
    }
}