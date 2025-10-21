<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;

class ClosingBalanceLine extends BalanceLine {

    public function getTable() {
        return "finance_accounting_closingbalanceline";
    }

    public static function getName() {
        return "Account Balance";
    }

    public static function getDescription() {
        return "A closing balance line corresponds to a specific accounting entry at the time of closure. ClosingBalanceLines capture the final impact of each account's transactions, ensuring a clear record of all balances at the closure point.";
    }

    public static function getColumns() {
        return [
            'balance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\ClosingBalanceLine',
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