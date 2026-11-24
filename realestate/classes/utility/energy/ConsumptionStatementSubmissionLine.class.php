<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use finance\accounting\Account;

class ConsumptionStatementSubmissionLine extends \equal\orm\Model {

    public static function getName() {
        return 'Consumption Statement Submission Line';
    }

    public static function getDescription() {
        return 'Optional submission lines are based on consumption file sections and are sent to the allocation provider to indicate how the statement must be apportioned under specific constraints. They define the allocation basis for a given accounting account, which is linked to an apportionment, by specifying the amount paid by the condominium and/or a quantity and unit price.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'description'    => 'Condominium the submission relates to.',
                'required'       => true
            ],

            'description' => [
                'type'           => 'string',
                'usage'          => 'text/plain',
                'description'    => 'Short text to communicate to the supplier in charge of the calculation.'
            ],

            'consumption_statement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionStatement',
                'description'    => 'Consumption statement the submission relates to.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'ondelete'       => 'cascade',
                'required'       => true
            ],

            'consumption_file_section_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFileSection',
                'description'    => 'Consumption file section from which the submission was generated.',
                'ondelete'       => 'null'
            ],

            'account_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\Account',
                'description'    => 'Accounting account the submitted amount relates to.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true
            ],

            'apportionment_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Apportionment',
                'description'    => 'Apportionment used to distribute the submitted amount.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Paid amount assigned to the the consumption expense.',
                'default'     => 0.0,
                'required'    => true
            ],

            'units' => [
                'type'        => 'string',
                'usage'       => 'text/plain:20',
                'description' => 'Units of the section in the consumption file.',
                'selection'   => [
                    'm3',
                    'l',
                    'hl',
                    'kWh'
                ]
            ],

            'calculation_method' => [
                'type'        => 'string',
                'usage'       => 'text/plain:20',
                'description' => 'Units of the section in the consumption file.',
                'selection'   => [
                    'unit_price',
                    'total_amount'
                ],
                'default'    => 'total_amount'
            ],

            'quantity' => [
                'type'        => 'float',
                'usage'       => 'number/real:2',
                'description' => 'Quantity, if known.'
            ],

            'unit_price' => [
                'type'        => 'float',
                'usage'       => 'amount/money:5',
                'description' => 'Submitted amount assigned to the accounting account and apportionment.',
                'default'     => 0.0,
                'visible'     => ['calculation_method', '=', 'unit_price']
            ]
        ];
    }

    public function getUniques(): array {
        return [
            ['consumption_statement_id', 'consumption_file_section_id']
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'sync_allocation_provider_information' => [
                'description' => 'Synchronize the allocation-provider information of the related consumption statement.',
                'policies'    => ['can_edit'],
                'function'    => 'doSyncAllocationProviderInformation'
            ]
        ]);
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_create' => [
                'description' => 'Checks that a submission line can be added to the statement.',
                'function'    => 'policyCanCreate'
            ],
            'can_edit' => [
                'description' => 'Checks that the statement submissions are still editable.',
                'function'    => 'policyCanEdit'
            ]
        ]);
    }

    public static function getOperationPolicies(): array {
        return [
            EQ_R_CREATE => ['can_create'],
            EQ_R_UPDATE => ['can_edit'],
            EQ_R_DELETE => ['can_edit']
        ];
    }

    protected static function onafterinstantiate($self) {
        $self->do('sync_allocation_provider_information');
    }

    protected static function onafterupdate($self) {
        $self->do('sync_allocation_provider_information');
    }

    protected static function doSyncAllocationProviderInformation($self) {
        $self->read(['consumption_statement_id']);
        $statement_ids = [];
        foreach($self as $submissionLine) {
            if($submissionLine['consumption_statement_id']) {
                $statement_ids[$submissionLine['consumption_statement_id']] = true;
            }
        }
        if(count($statement_ids)) {
            ConsumptionStatement::ids(array_keys($statement_ids))
                ->do('sync_allocation_provider_information');
        }
    }

    public static function onchange($event, $values): array {
        $result = [];

        if(isset($event['account_id'])) {
            $account = Account::id($event['account_id'])
                ->read(['apportionment_id' => ['id', 'name']])
                ->first();

            if($account && $account['apportionment_id']) {
                $result['apportionment_id'] = [
                    'id'   => $account['apportionment_id']['id'],
                    'name' => $account['apportionment_id']['name']
                ];
            }
        }

        return $result;
    }

    protected static function policyCanCreate($values): array {
        $statement_id = $values['consumption_statement_id'] ?? null;
        if(is_array($statement_id)) {
            $statement_id = $statement_id['id'] ?? null;
        }

        $statement = $statement_id
            ? ConsumptionStatement::id($statement_id)->read(['status'])->first()
            : null;

        if(!$statement || !in_array($statement['status'], ['draft', 'proforma'], true)) {
            return ['statement_submissions_locked'];
        }
        return [];
    }

    protected static function policyCanEdit($self): array {
        $result = [];
        $self->read(['consumption_statement_id' => ['status']]);
        foreach($self as $id => $submission) {
            if(
                !$submission['consumption_statement_id']
                || !in_array($submission['consumption_statement_id']['status'], ['draft', 'proforma'], true)
            ) {
                $result[$id]['statement_submissions_locked'] = 'Submissions can only be edited while the statement is in draft or proforma.';
            }
        }
        return $result;
    }
}
