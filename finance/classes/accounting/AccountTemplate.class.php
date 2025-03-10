<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
                'description'       => "Code of the default apportionment key to use (implied in retrieval of related apportionment_id)."
            ],

        ];
    }
}