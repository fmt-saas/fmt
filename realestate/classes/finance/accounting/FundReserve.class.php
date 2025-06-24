<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\finance\accounting;

use finance\accounting\Account;
use realestate\property\Apportionment;

class FundReserve extends CondoFund {

    public static function getName() {
        return 'Reserve Fund';
    }

    public static function getDescription() {
        return "A Reserve Fund is used by a Condominium as a link between a reserve fund account, an utilization account and an apportionment key.";
    }

    public static function getColumns() {
        return [
            'fund_type' => [
                'type'              => 'string',
                'description'       => "Type of fund the entry relates to.",
                'help'              => "There might be as many funds as necessary, for each type of fund.",
                'default'           => 'reserve_fund'
            ]
        ];
    }

    protected static function computeExpenseAccountId($fund_account_id) {
        $result = null;
        $fundAccount = Account::id($fund_account_id)
            ->read(['id', 'code', 'condo_id'])
            ->first();

        if($fundAccount) {
            // #todo #accounting - we should not hard coding this
            $expense_account_code = '68' . $fundAccount['code'] . '1';
            $expenseAccount = Account::search([['condo_id', '=', $fundAccount['condo_id']], ['code', '=', $expense_account_code]])->first();
            if($expenseAccount) {
                $result = $expenseAccount['id'];
            }
        }

        return $result;
    }


}
