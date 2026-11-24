<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use finance\accounting\Account;

class ConsumptionFileSection extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'description'    => 'Condominium the section belongs to.',
                'required'       => true,
                'readonly'       => true
            ],

            'name' => [
                'type'        => 'string',
                'usage'       => 'text/plain:30',
                'description' => 'Name identifying the accounting section in the consumption file.',
                'selection'   => [
                    'heating',
                    'heating_and_hot_water',
                    'hot_water',
                    'cold_water'
                ],
                'multilang'   => true,
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

            'consumption_file_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFile',
                'description'    => 'Consumption file the section belongs to.',
                'domain'         => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['condo_id', '<>', null]
                ],
                'ondelete'       => 'cascade',
                'required'       => true
            ],

            'accounting_account_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'finance\accounting\Account',
                'description'    => 'Accounting account associated with the section.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'onupdate'       => 'onupdateAccountingAccountId',
                'required'       => true
            ],

            'apportionment_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Apportionment',
                'description'    => 'Apportionment used to distribute the allocated amount.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true
            ]

        ];
    }

    public function getUnique() {
        return [
            ['consumption_file_id', 'name'],
            ['consumption_file_id', 'accounting_account_id']
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'sync_draft_allocations' => [
                'description' => 'Synchronize draft statement submission lines with this section.',
                'policies'    => [],
                'function'    => 'doSyncDraftAllocations'
            ],
            'remove_draft_allocations' => [
                'description' => 'Remove draft statement submission lines originating from this section.',
                'policies'    => [],
                'function'    => 'doRemoveDraftAllocations'
            ]
        ]);
    }

    public static function onchange($event, $values): array {
        $result = [];

        if(array_key_exists('accounting_account_id', $values)) {
            $account_id = is_array($values['accounting_account_id'])
                ? ($values['accounting_account_id']['id'] ?? null)
                : $values['accounting_account_id'];

            if(!$account_id) {
                return $result;
            }

            $account = Account::id($account_id)
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

    protected static function oninstantiate($self) {
        $self->do('sync_draft_allocations');
    }

    protected static function onupdateAccountingAccountId($self) {
        $self->read(['accounting_account_id' => ['apportionment_id']]);

        foreach($self as $id => $section) {
            if(!$section['accounting_account_id'] || !$section['accounting_account_id']['apportionment_id']) {
                continue;
            }

            self::id($id)->update([
                'apportionment_id' => $section['accounting_account_id']['apportionment_id']
            ]);
        }
    }

    protected static function onbeforeupdate($self, $values) {

        if(!array_key_exists('consumption_file_id', $values)) {
            return;
        }

        $new_consumption_file_id = $values['consumption_file_id'] ?? null;
        $self->read(['consumption_file_id']);
        foreach($self as $id => $section) {
            if($section['consumption_file_id'] !== $new_consumption_file_id) {
                self::id($id)->do('remove_draft_allocations');
            }
        }
    }

    protected static function onafterupdate($self) {
        $self->do('sync_draft_allocations');
    }

    protected static function onbeforedelete($self) {
        $self->do('remove_draft_allocations');
    }

    protected static function doSyncDraftAllocations($self) {
        $self->read(['consumption_file_id', 'condo_id', 'accounting_account_id']);
        foreach($self as $section_id => $section) {
            if(!$section['consumption_file_id'] || !$section['condo_id'] || !$section['accounting_account_id']) {
                continue;
            }

            $account = Account::id($section['accounting_account_id'])
                ->read(['apportionment_id'])
                ->first();
            $apportionment_id = $account ? $account['apportionment_id'] : null;

            $statements = ConsumptionStatement::search([
                    ['consumption_file_id', '=', $section['consumption_file_id']],
                    ['status', '=', 'draft']
                ])
                ->read(['id']);

            foreach($statements as $statement_id => $statement) {
                $submissionLine = ConsumptionStatementSubmissionLine::search([
                        ['consumption_statement_id', '=', $statement_id],
                        ['consumption_file_section_id', '=', $section_id]
                    ])
                    ->first();

                $values = [
                    'condo_id'         => $section['condo_id'],
                    'account_id'       => $section['accounting_account_id'],
                    'apportionment_id' => $apportionment_id
                ];

                if($submissionLine) {
                    ConsumptionStatementSubmissionLine::id($submissionLine['id'])->update($values);
                    continue;
                }

                ConsumptionStatementSubmissionLine::create(array_merge($values, [
                    'consumption_statement_id'    => $statement_id,
                    'consumption_file_section_id' => $section_id,
                    'amount'                      => 0.0
                ]));
            }
        }
    }

    protected static function doRemoveDraftAllocations($self) {
        $self->read(['consumption_file_id']);
        foreach($self as $section_id => $section) {
            $statement_ids = ConsumptionStatement::search([
                    ['consumption_file_id', '=', $section['consumption_file_id']],
                    ['status', '=', 'draft']
                ])
                ->ids();

            if(!count($statement_ids)) {
                continue;
            }

            ConsumptionStatementSubmissionLine::search([
                    ['consumption_statement_id', 'in', $statement_ids],
                    ['consumption_file_section_id', '=', $section_id]
                ])
                ->delete(true);
        }
    }

}
