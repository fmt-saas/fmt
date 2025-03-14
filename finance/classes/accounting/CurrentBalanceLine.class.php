<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;

class CurrentBalanceLine extends BalanceLine {

    public function getTable() {
        return "finance_accounting_currentbalanceline";
    }

    public static function getName() {
        return "Account Balance";
    }

    public static function getDescription() {
        return "Lines of the CurrentBalance are synchronized with the accounting entries recorded in the respective accounts, representing each transaction's impact..";
    }

    public static function getColumns() {
        return [
            'balance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\CurrentBalanceLine',
                'required'          => true,
                'ondelete'          => 'cascade'
            ]
        ];
    }

}