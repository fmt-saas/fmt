<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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
                'description'       => "The organisation the chart belongs to."
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
                'help'              => 'This code serve as unique and absolute identifier, and can also be used to match journal in an external tool.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'journal_type' => [
                'type'              => 'string',
                'selection'         => [
                    'LEDG'      => 'General Ledger',
                    'SALE'      => 'Sales',
                    'PURC'      => 'Purchases',
                    'BANK'      => 'Bank',
                    'CASH'      => 'Cashdesk',
                    'PAYR'      => 'Payroll',
                    'ASST'      => 'Fixed Assets',
                    'OPEN'      => 'Opening Balances/Carryforward',
                    'MISC'      => 'Miscellaneous operations'
                ],
                "required"          => true,
                'description'       => "Type of journal or ledger.",
                'dependents'        => ['name']
            ],

            'accounting_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'visible'           => ['journal_type', '=', 'BANK']
            ],

            'is_visible' => [
                'type'              => 'boolean',
                'description'       => "Flag to switch visibility of the journal.",
                'default'           => true
            ],

            'accounting_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountingEntry',
                'foreign_field'     => 'journal_id',
                'description'       => "Accounting entries of the journal."
            ],

            // #todo - add 'default_account_id' ?

            // support for sub journals
            // for now this is limited to 1 level, and for CASH/FIN journals only
            // creation of sub-journals is not allowed through the UI and is made programmatically only to have 1 sub-journal for each accounting account linked to condominiums bank accounts
            'has_parent' => [
                'type'              => 'boolean',
                'description'       => "Flag mak journal has sub-journal.",
                'default'           => false,
                'visible'           => ['journal_type', '=', 'BANK']
            ],

            'parent_journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "The journal the accounting journal is a child of.",
                'readonly'          => true,
                'visible'           => ['has_parent', '=', true]
            ],

            'sub_journals_ids' => [
                'type'              => 'one2many',
                'description'       => "The children accounting journals, if any.",
                'foreign_object'    => 'finance\accounting\Journal',
                'foreign_field'     => 'parent_journal_id'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['mnemo', 'code', 'journal_type', 'description']);
        foreach($self as $id => $journal) {
            $parts = [];
            if($journal['mnemo'] && strlen($journal['mnemo'])) {
                $parts[] = $journal['mnemo'];
            }
            $parts[] = $journal['code'];
            if($journal['description'] && strlen($journal['description'])) {
                $parts[] = $journal['description'];
            }
            $parts[] = $journal['journal_type'];
            $result[$id] = implode(' - ', $parts);
        }
        return $result;
    }

    public function getUnique(): array {
        return [
            ['code', 'organisation_id', 'condo_id']
        ];
    }
}