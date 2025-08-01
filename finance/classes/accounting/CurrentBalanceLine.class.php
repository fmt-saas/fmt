<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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