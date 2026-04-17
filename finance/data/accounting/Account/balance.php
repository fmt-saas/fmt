<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\accounting\Account;
use finance\accounting\AccountBalanceChange;
use finance\accounting\OpeningBalance;
use finance\accounting\OpeningBalanceLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Get account balance at a given date.',
    'params'        => [

        'id' =>  [
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\Account',
            'description'       => '',
            'required'          => true
        ],

        'date' => [
            'type'          => 'date',
            'description'   => '',
            'required'       => true
        ],

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);
/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$result = [
    'id'        => $params['id'],
    'date'      => date('c', $params['date']),
    'balance'   => 0.0
];

$account_id = $params['id'];
$account = Account::id($account_id)->read(['condo_id'])->first();

if(!$account) {
    throw new Exception('unknown_account', EQ_ERROR_INVALID_PARAM);
}

/*
* 1. Try to find latest balance change <= date
*/
$change = AccountBalanceChange::search([
            ['condo_id', '=', $account['condo_id']],
            ['account_id', '=', $account_id],
            ['date', '<=', $params['date']]
        ],
        [
            'sort'  => ['date' => 'desc'],
            'limit' => 1
        ]
    )
    ->read(['debit_balance', 'credit_balance'])
    ->first();

if($change) {
    $result['balance'] = (float) $change['debit_balance'] - (float) $change['credit_balance'];
}
else {
    // Fallback: Opening Balance
    $openingBalance = OpeningBalance::search([
                ['condo_id', '=', $account['condo_id']],
                ['status', '=', 'validated']
            ],
            [
                'sort'  => ['created' => 'desc'],
                'limit' => 1
            ]
        )
        ->first();

    if($openingBalance) {
        $line = OpeningBalanceLine::search([
                ['opening_balance_id', '=', $openingBalance['id']],
                ['account_id', '=', $account_id]
            ])
            ->read(['debit_balance', 'credit_balance'])
            ->first();

        if($line) {
            $result['balance'] = (float) $line['debit_balance'] - (float) $line['credit_balance'];
        }
    }
}


$context->httpResponse()
        ->body($result)
        ->send();
