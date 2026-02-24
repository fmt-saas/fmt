<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountBalanceChange extends Model {

    public static function getName() {
        return "Account Balance Change";
    }

    public static function getDescription() {
        return "AccountBalanceChange lines represent the cumulative balance of an account at each date where at least one accounting transaction has been recorded.
            Each line reflects the total debit and credit amounts of the account after all transactions of that date have been applied. Rather than storing a balance for every calendar day, a line is created only when the account balance effectively changes.
            These records act as an incremental time-series of cumulative balances. They allow the system to retrieve the balance of an account at any arbitrary date by simply selecting the most recent balance entry prior to (or equal to) that date, without recomputing all underlying accounting entries.
            The logic is independent of fiscal years or accounting periods. It is purely driven by transactions recorded on the account and is updated in real time whenever transactions are validated or cancelled.
            This mechanism ensures both performance and consistency, while always reflecting the exact financial position of an account at a given point in time.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry line refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the balance line relates to.",
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date at which the cumulative balance is valid, after applying all transactions recorded on that day.',
                'required'          => true
            ],

            'debit_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total cumulative debit amount of the account up to and including this date.',
                'default'           => 0.0,
                'required'          => true
            ],

            'credit_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total cumulative credit amount of the account up to and including this date.',
                'default'           => 0.0,
                'required'          => true
            ]

        ];
    }

    public function getUnique() {
        return [
            ['account_id', 'condo_id', 'date']
        ];
    }


// #todo
    protected static function doRebuildBalanceProjection($values) {

        $condo_id = $values['condo_id'];
        $mode = $params['mode'] ?? 'from_closing';

        // 1) Resolve anchor date
        $anchor_date = null;

        if ($mode === 'from_closing') {
            $anchor_date = ClosingBalance::getLastClosingDate($condo_id); // method to implement
            if (!$anchor_date) {
                $mode = 'full';
            }
        }
        if ($mode === 'from_date') {
            $anchor_date = $params['date_from'] ?? null;
        }

        // 2) Purge projection after anchor
        if ($anchor_date) {
            self::deleteWhere([
                ['condo_id', '=', $condo_id],
                ['date', '>', $anchor_date]
            ]);
        } else {
            self::deleteWhere([['condo_id', '=', $condo_id]]);
        }

        // 3) Inject anchor balances
        if ($anchor_date) {
            $closing_lines = ClosingBalance::getBalancesAtDate($condo_id, $anchor_date);
            foreach ($closing_lines as $line) {
                self::create([[
                    'condo_id' => $condo_id,
                    'account_id' => $line['account_id'],
                    'date' => $anchor_date,
                    'debit_balance' => $line['debit_balance'],
                    'credit_balance' => $line['credit_balance']
                ]]);
            }
        }

        // 4) Replay validated postings after anchor
        $domain = [
            ['condo_id', '=', $condo_id],
            ['status', '=', 'validated'],
        ];
        if ($anchor_date) {
            $domain[] = ['entry_date', '>', $anchor_date];
        }

        // Stream lines ordered by date, aggregate (account_id, entry_date)
        // Update / create ABC cumulatively.

        return ['status' => 'ok', 'anchor_date' => $anchor_date];
    }
// #todo
    protected static function doAuditBalanceProjection($values) {

        $condo_id = $values['condo_id'];
        $scope = $values['scope'] ?? 'closings';
        $tolerance = $values['tolerance'] ?? 0.01;

        $issues = [];

        if ($scope === 'closings') {

            $closings = ClosingBalance::getClosings($condo_id); // list of closing dates
            foreach ($closings as $closing_date) {

                $closing_map = ClosingBalance::getBalancesMap($condo_id, $closing_date);
                $projection_map = self::getBalancesMapAtDate($condo_id, $closing_date);

                foreach ($closing_map as $account_id => $expected) {
                    $actual = $projection_map[$account_id] ?? ['debit'=>0.0,'credit'=>0.0];

                    if (abs($expected['debit'] - $actual['debit']) > $tolerance ||
                        abs($expected['credit'] - $actual['credit']) > $tolerance) {

                        $issues[] = [
                            'type' => 'closing_mismatch',
                            'date' => $closing_date,
                            'account_id' => $account_id,
                            'expected' => $expected,
                            'actual' => $actual
                        ];
                    }
                }
            }
        }

        $status = empty($issues) ? 'ok' : 'error';

        // Optional auto-fix
        if (!empty($params['fix']) && $status !== 'ok') {
            self::actionRebuildBalanceProjection([
                'condo_id' => $condo_id,
                'mode' => 'from_closing'
            ]);
            return ['status' => 'fixed', 'issues' => $issues];
        }

        return ['status' => $status, 'issues' => $issues];
    }

}
