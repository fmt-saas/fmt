<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;

class CurrentBalance extends Balance {

    public function getTable() {
        return "finance_accounting_currentbalance";
    }

    public static function getName() {
        return "Current Balance";
    }

    public static function getDescription() {
        return "An up-to-date balance for a given fiscal year. The CurrentBalance reflects the ongoing status of debits and credits on the accounts for the current period.";
    }

    public static function getColumns() {
        return [

            'balance_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\CurrentBalanceLine',
                'foreign_field'     => 'balance_id',
                'description'       => "Lines of the balance."
            ]

        ];
    }

}