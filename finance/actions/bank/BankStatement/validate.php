<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\BankStatement;

[$params, $providers] = eQual::announce([
    'description'   => "Validate consistency and completeness of a bank statement.",
    'help'          => "This controller is meant to be called as a result of a ValidationRule verification.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'description'       => "The bank statement to validate.",
            'foreign_object'    => 'finance\bank\BankStatement',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context         $context
 * @var \equal\dispatch\Dispatcher $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

// target object identifier
$id     = $params['id'];
$class  = 'finance\bank\BankStatement';
$script = 'finance_bank_BankStatement_validate';

$bankStatement = BankStatement::id($id)
    ->read([
        'status',
        'condo_id',
        'opening_balance',
        'closing_balance',
        'statement_lines_ids' => [
            'amount',
            'is_income',
            'is_expense',
            'apportionment_id'
        ]
    ])
    ->first();

if(!$bankStatement) {
    throw new Exception("unknown_bank_statement", EQ_ERROR_UNKNOWN_OBJECT);
}

// Validate statement lines
$linesTotal = 0.0;
$missingApportionment = false;

foreach($bankStatement['statement_lines_ids'] as $line) {

    $linesTotal += (float) $line['amount'];

    if($line['is_income'] || $line['is_expense']) {
        if(!$line['apportionment_id']) {
            $missingApportionment = true;
        }
    }
}

if($missingApportionment) {
    $dispatch->dispatch(
        'finance.bank.statement.missing_apportionment',
        $class,
        $id,
        'important',
        $script,
        ['id' => $id]
    );
}
else {
    $dispatch->cancel('finance.bank.statement.missing_apportionment', $class, $id);
}

// Validate balances consistency
$expectedClosing =
    (float) $bankStatement['opening_balance']
    + $linesTotal;

// tolerance for float comparison
$epsilon = 0.0001;

if(abs($expectedClosing - (float) $bankStatement['closing_balance']) > $epsilon) {

    $dispatch->dispatch(
        'finance.bank.statement.inconsistent_balance',
        $class,
        $id,
        'important',
        $script,
        [
            'id'                => $id,
            'opening_balance'   => $bankStatement['opening_balance'],
            'computed_closing'  => $expectedClosing,
            'closing_balance'   => $bankStatement['closing_balance']
        ]
    );

    throw new Exception(
        "inconsistent_bank_statement_balance",
        EQ_ERROR_INVALID_PARAM
    );
}
else {
    $dispatch->cancel('finance.bank.statement.inconsistent_balance', $class, $id);
}


$context->httpResponse()
    ->status(204)
    ->send();
