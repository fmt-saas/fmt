<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\order;

class ContractLineGroup extends \sale\contract\ContractLineGroup {

    public static function getColumns() {

        return [

            'contract_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\order\Contract',
                'description'       => 'The contract the line relates to.',
            ],

            'contract_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\order\ContractLine',
                'foreign_field'     => 'contract_line_group_id',
                'description'       => 'Contract lines that belong to the contract.',
                'ondetach'          => 'delete'
            ],

            'fare_benefit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount of the fare benefit VAT incl.',
                'default'           => 0.0
            ]

        ];
    }

}
