<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
            ]
        ];
    }
}
