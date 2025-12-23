<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\processing\DocumentProcess;
use finance\bank\BankStatement;

[$params, $providers] = eQual::announce([
    'description'   => "Validate consistency and completeness of a bank statement.",
    'help'          => "This controller is meant to be called as a result of a ValidationRule verification.",
    'params'        => [
        'id' =>  [
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
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

// target objet identifier
$id = $params['id'];
// target object class
$class = 'finance\bank\BankStatement';
// current script for reference in dispatches
$script = 'finance_bank_BankStatement_validate';


$bankStatement = BankStatement::id($id)
    ->read([
        'status',
        'document_process_id',
        'condo_id',
        'opening_balance',
        'closing_balance',
        'statement_lines_ids' => [
            'amount', 'is_income', 'is_expense', 'apportionment_id'
        ]
    ])
    ->first();

if(!$bankStatement) {
    throw new Exception("unknown_invoice", EQ_ERROR_UNKNOWN_OBJECT);
}

$lines_total = 0.0;

foreach($bankStatement['statement_lines_ids'] as $expense_statement_line_id => $expenseStatementLine) {
    if($expenseStatementLine['is_income'] || $expenseStatementLine['is_expense']) {
        if(!$expenseStatementLine['apportionment_id']) {
    // $dispatch->dispatch('purchase.accounting.invoice.missing_apportionment', $class, $id, 'important', $script, ['id' => $id]);
        }
    }

}
// symmetrical removal of the alerts (if any)
$dispatch->cancel('finance.bank.statement.duplicate_expense_account', $class, $id);
$dispatch->cancel('finance.bank.statement.missing_apportionment', $class, $id);
$dispatch->cancel('finance.bank.statement.missing_expense_account', $class, $id);


if($usage_total > $bankStatement['price']) {
    // error: Fund usage cannot exceed invoice total
    $dispatch->dispatch('finance.bank.statement.exceeding_fund_allocation', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("exceeding_fund_allocation", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('finance.bank.statement.exceeding_fund_allocation', $class, $id);
}


if($previousBankStatement) {
    // invoice is a possible duplicate: issue an alert
    $links = [
        "[{$previousBankStatement['supplier_invoice_number']}](/app/#/accounting/purchase-invoice/{$previousBankStatement['id']})"
    ];
    $dispatch->dispatch('purchase.accounting.invoice.possible_duplicate_invoice', $class, $id, 'important', null, [], $links);
}


// #memo - the `alert` field should be forced to be refreshed upon each validation attempt

// a 2xx response mean validation was successful, in all other cases, an Exception is raised
$context->httpResponse()
        ->status(204)
        ->send();
