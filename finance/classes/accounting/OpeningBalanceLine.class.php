<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class OpeningBalanceLine extends BalanceLine {

    public function getTable() {
        return "finance_accounting_openingbalanceline";
    }

    public static function getName() {
        return "Opening Balance Line";
    }

    public static function getDescription() {
        return "An opening balance line represents the starting balance of a specific account at the beginning of a fiscal year. OpeningBalanceLines record the carried-forward debit or credit balance from the previous closing balance, establishing the initial state of each account for the new accounting period.";
    }

    public static function getColumns() {
        return [
            'balance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\OpeningBalanceLine',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],
        ];
    }

    /**
     * A line can only be deleted if its parent balance status is pending.
     */
    public static function candelete($self) {
        $self->read(['balance_id' => ['status']]);
        foreach($self as $id => $line) {
            if($line['balance_id']['status'] != 'pending') {
                return [
                    'status' => 'Lines from a validated balance cannot be deleted.'
                ];
            }
        }
        return parent::candelete($self);
    }

}