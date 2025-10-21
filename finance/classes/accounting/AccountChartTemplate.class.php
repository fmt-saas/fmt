<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class AccountChartTemplate extends AccountChart {

    public function getTable() {
        return 'finance_accounting_account_chart_template';
    }

    public static function getColumns() {
        return [
          'accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountTemplate',
                'foreign_field'     => 'account_chart_id',
                'description'       => 'Account lines that belong to the chart.',
                'ondetach'          => 'delete'
            ],

            'status' => [
                'type'              => 'string',
                'default'           => 'active'
            ],

        ];
    }
}
