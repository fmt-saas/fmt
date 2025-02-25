<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\contract;

use equal\orm\Model;

class ContractLineGroup extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'The display name of the group.'
            ],

            'contract_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\contract\Contract',
                'description'       => 'The contract the line relates to.',
            ],

            'contract_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\contract\ContractLine',
                'foreign_field'     => 'contract_line_group_id',
                'description'       => 'Contract lines that belong to the contract.',
                'ondetach'          => 'delete'
            ]

        ];
    }
}
