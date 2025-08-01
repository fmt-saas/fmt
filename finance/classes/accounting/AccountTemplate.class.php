<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class AccountTemplate extends Account {

    public function getTable() {
        return 'finance_accounting_account_template';
    }

    public static function getColumns() {

        return [
            'parent_account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountTemplate',
                'description'       => "The parent account (line) the account is part of.",
                'dependents'        => ['level']
            ],

            'children_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountTemplate',
                'foreign_field'     => 'parent_account_id',
                'description'       => "The children accounts linked to the account (next level)."
            ],

            'account_chart_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountChartTemplate',
                'description'       => "The chart of accounts the line belongs to.",
                'help'              => "The account chart is the parent chart of accounts template the account template is part of.",
                'required'          => true
            ],

            'apportionment_code' => [
                'type'              => 'string',
                'description'       => "Code of the default apportionment key to use.",
                'help'              => "The code is mandatory and implied in retrieval of related apportionment_id."
            ],

        ];
    }

    /**
     * #memo - discard condo_id from unique key
     */
    public function getUnique() {
        return [
            ['code']
        ];
    }
}