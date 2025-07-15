<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\finance\budget;


class CondoBudget extends \equal\orm\Model {

    public static function getName() {
        return 'Condominium Budget';
    }

    public static function getDescription() {
        return "The Budget represents the expected expenses for a specific fiscal year.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Explanation or internal notes about the budget.',
                'default'           => 'New Budget'
            ],

            'fiscal_year_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\FiscalYear',
                'description'       => 'Fiscal year in which the budget is planned.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'budget_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\budget\CondoBudgetEntry',
                'foreign_field'     => 'condo_budget_id',
                'description'       => 'Fiscal year in which the budget is planned.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'published',
                    'validated'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the budget.',
            ],

        ];
    }

    public static function getActions() {
        return [
            'duplicate_budget' => [
                'description'   => 'Creates accounting entries for the refund.',
                'policies'      => [/* 'can_generate_accounting_entry' */],
                'function'      => 'doDuplicateBudget'
            ],
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the state of the Budget allows publication.',
                'function'    => 'policyIsValid'
            ]
        ];
    }


    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Budget being completed, waiting to be published.',
                'icon'        => 'done',
                'transitions' => [
                    'publish' => [
                        'description' => 'Update the fund to `published`.',
                        'policies'    => ['is_valid'],
                        'status'      => 'published'
                    ]
                ]
            ]
        ];
    }

    protected static function policyIsValid($self): array {
        $result = [];
        $self->read(['status', 'condo_id', 'fiscal_year_id']);
        foreach($self as $id => $condoBudget) {
            $matches = self::search([
                ['condo_id', '=', $condoBudget['condo_id']],
                ['fiscal_year_id', '=', $condoBudget['fiscal_year_id']],
                ['id', '<>', $id]
            ]);

            if($matches->count() > 0) {
                $result[$id] = [
                    'duplicate_budget' => 'Another budget already exists for same fiscal year.'
                ];
            }
        }
        return $result;
    }

    protected static function doDuplicateBudget($self) {
        $self->read(['condo_id', 'name', 'fiscal_year_id', 'budget_entries_ids' => ['entry_account_id', 'name', 'amount']]);

        foreach($self as $id => $condoBudget) {
            $newBudget = self::create([
                    'condo_id'          => $condoBudget['condo_id'],
                    'fiscal_year_id'    => $condoBudget['fiscal_year_id'],
                    'name'              => $condoBudget['name'] . ' (copie)'
                ])
                ->first();

            foreach($condoBudget['budget_entries_ids'] as $budget_entry_id => $budgetEntry) {
                CondoBudgetEntry::create([
                        'condo_id'          => $condoBudget['condo_id'],
                        'condo_budget_id'   => $newBudget['id'],
                        'entry_account_id'  => $budgetEntry['entry_account_id'],
                        'name'              => $budgetEntry['name'],
                        'amount'            => $budgetEntry['amount'],
                    ]);
            }
        }
    }

    public static function onchange($event, $values) {
        $result = [];

        if(array_key_exists('condo_id', $event)) {
            if(!$event['condo_id']) {
                $result['fiscal_year_id'] = null;
            }
        }

        return $result;
    }

}
