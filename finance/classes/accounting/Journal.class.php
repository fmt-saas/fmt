<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class Journal extends Model {

    public static function getName() {
        return "Accounting journal";
    }

    public static function getDescription() {
        return "An accounting journal is used to group accounting entries based on their nature.
        Journals are common to all entries and serve primarily as a means of filtering and routing the accounting entries to the appropriate categories.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting journal refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the chart belongs to.",
                'default'           => 1
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Label for identifying the journal.',
                'function'          => 'calcName',
                'store'             => true,
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Verbose detail of the role of the journal.',
                'multilang'         => true,
                'dependents'        => ['name']
            ],

            'mnemo' => [
                'type'              => 'string',
                'description'       => 'Short mnemonic used to prefix name and use as reference.',
                'multilang'         => true,
                'dependents'        => ['name']
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code.',
                'help'              => 'This can also be used to match journal in an external tool.',
                'unique'            => true,
                'required'          => true,
                'dependents'        => ['name']
            ],

            'journal_type' => [
                'type'              => 'string',
                'selection'         => [
                    'LEDG'      => 'General Ledger',
                    'SALE'      => 'Sales',
                    'PURC'      => 'Purchases',
                    'CASH'      => 'Bank & Cash',
                    'PAYR'      => 'Payroll',
                    'ASST'      => 'Fixed Assets',
                    'OPEN'      => 'Opening Balances/Carryforward',
                    'MISC'      => 'Miscellaneous operations'
                ],
                "required"          => true,
                'description'       => "Type of journal or ledger.",
                'dependents'        => ['name']
            ],

            'is_visible' => [
                'type'              => 'boolean',
                'description'       => "Flag to switch visibility of the account.",
                'default'           => true
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'journal_id',
                'description'       => "Accounting entries of the journal.",
                'required'          => true
            ]

            // #todo - add 'default_account_id'

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['mnemo', 'code', 'journal_type', 'description']);
        foreach($self as $id => $journal) {
            $name = ($journal['description'] && strlen($journal['description'])) ? $journal['mnemo'] : $journal['code'];
            if($journal['description'] && strlen($journal['description'])) {
                $name .= ' - '.$journal['description'];
            }
            $name .= ' ('.$journal['journal_type'].')';
            $result[$id] = $name;
        }
        return $result;
    }

}