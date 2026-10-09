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

            'consumption_file_section_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFileSection',
                'description'    => 'Consumption file section from which the allocation was generated.',
                'ondelete'       => 'null'
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
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Amount allocated to the accounting account and apportionment.',
                'default'     => 0.0,
                'required'    => true
            ]
        ];
    }

    public function getUnique() {
        return [
            ['consumption_statement_id', 'consumption_file_section_id']
        ];
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_edit' => [
                'description' => 'Checks that the statement allocations are still editable.',
                'function'    => 'policyCanEdit'
            ]
        ]);
    }

    public static function getOperationPolicies(): array {
        return [
            EQ_R_UPDATE => ['can_edit'],
            EQ_R_DELETE => ['can_edit']
        ];
    }

    protected static function policyCanEdit($self): array {
        $result = [];
        $self->read(['consumption_statement_id' => ['status']]);
        foreach($self as $id => $allocation) {
            if(
                !$allocation['consumption_statement_id']
                || !in_array($allocation['consumption_statement_id']['status'], ['draft', 'proforma'], true)
            ) {
                $result[$id]['statement_allocations_locked'] = 'Allocations can only be edited while the statement is in draft or proforma.';
            }
        }
        return $result;
    }
}
