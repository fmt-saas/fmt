<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class FiscalPeriod extends Model {

    public static function getName() {
        return "Fiscal Period";
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the fiscal period refers to.",
                'help'              => "When a fiscal year is not linked to a condominium, it relates to the organisation itself.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to.",
                'default'           => 1
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => "The organisation the chart belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'Label for identifying the fiscal year.',
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'First day (included) of the fiscal year.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Last day (included) of the period.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'code' => [
                'type'              => 'integer',
                'description'       => 'Order of the period, based on its date within the fiscal year.',
                'help'              => 'This value is assigned by parent Fiscal Year, and is needed for purchase invoice sequence numbering.',
                'dependents'        => ['name']
            ],

            'status' => [
                'type'        => 'string',
                'selection'   => [
                    'pending',
                    'closed'
                ],
                'default'     => 'pending',
                'description' => 'Status of the accounting period.',
                'help'        => 'Status is `closed` once the expense statement has been validated.'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['code', 'date_from', 'date_to', 'condo_id' => ['name']]);
        foreach($self as $id => $period) {
            if(!$period['date_from'] || !$period['date_to']) {
                continue;
            }
            $result[$id] = $period['code'] . ' - ' . date('Y-m-d', $period['date_from']) . ' - ' . date('Y-m-d', $period['date_to']) . " ({$period['condo_id']['name']})";
        }
        return $result;
    }

}