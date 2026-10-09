<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

class ConsumptionStatementSubmissionLine extends \equal\orm\Model {

    public static function getName() {
        return 'Consumption Statement Submission Line';
    }

    public static function getDescription() {
        return 'Submitted consumption statement amount assigned to an accounting account and an apportionment.';
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
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true
            ],

            'amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Submitted amount assigned to the accounting account and apportionment.',
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
                    'total_mount'
                ],
                'default'    => 'total_mount'
            ],

            'quantity' => [
                'type'        => 'float',
                'usage'       => 'number/real:2',
                'description' => 'Quantity, if known.',
                'visible'     => ['calculation_method', '=', 'unit_price']
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

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_edit' => [
                'description' => 'Checks that the statement submissions are still editable.',
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
