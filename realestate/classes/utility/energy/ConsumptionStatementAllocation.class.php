<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

class ConsumptionStatementAllocation extends \equal\orm\Model {

    public static function getName() {
        return 'Consumption Statement Allocation';
    }

    public static function getDescription() {
        return 'Allocation of a consumption statement amount to an accounting account and an apportionment.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'description'    => 'Condominium the allocation relates to.',
                'required'       => true
            ],

            'consumption_statement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatement',
                'description'    => 'Consumption statement the allocation relates to.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'ondelete'       => 'cascade',
                'required'       => true
            ],

            'account_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\Account',
                'description'    => 'Accounting account the allocated amount relates to.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true
            ],

            'apportionment_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Apportionment',
                'description'    => 'Apportionment used to distribute the allocated amount.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true
            ],

            'amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Amount allocated to the accounting account and apportionment.',
                'required'    => true
            ]
        ];
    }
}
