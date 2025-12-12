<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => "Validate consistency and completeness of a purchase invoice.",
    'help'          => "This controller is meant to be called as a result of a ValidationRule verification.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The invoice to validate.",
            'foreign_object'    => 'realestate\purchase\accounting\invoice\PurchaseInvoice',
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
$class = 'realestate\purchase\accounting\invoice\PurchaseInvoice';
// current script for reference in dispatches
$script = 'realestate_purchase_accounting_invoice_PurchaseInvoice_validate';


$purchaseInvoice = PurchaseInvoice::id($id)
    ->read([
        'status',
        'invoice_type',
        'condo_id',
        'condo_bank_account_id',
        'suppliership_bank_account_id',
        'supplier_invoice_number',
        'payable_amount',
        'fiscal_year_id',
        'emission_date',
        'price',
        'invoice_lines_ids' => [
            'total', 'price', 'vat_rate', 'owner_share', 'tenant_share'
        ],
        'fund_usage_lines_ids' => [
            'fund_account_id', 'amount', 'apportionment_id', 'expense_account_id'
        ]
    ])
    ->first();

if(!$purchaseInvoice) {
    throw new Exception("unknown_invoice", EQ_ERROR_UNKNOWN_OBJECT);
}

if(strlen($purchaseInvoice['invoice_type']) <= 0) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_invoice_type', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_invoice_type", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_invoice_type', $class, $id);
}

if(!$purchaseInvoice['condo_id']) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_condo_id', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_condo_id", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_condo_id', $class, $id);
}

if(!$purchaseInvoice['fiscal_year_id']) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_fiscal_year_id', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_fiscal_year_id", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_fiscal_year_id', $class, $id);
}

if(!$purchaseInvoice['condo_bank_account_id']) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_condo_bank_account_id', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_condo_bank_account_id", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_condo_bank_account_id', $class, $id);
}

if(!$purchaseInvoice['suppliership_bank_account_id']) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_suppliership_bank_account_id', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_suppliership_bank_account_id", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_suppliership_bank_account_id', $class, $id);
}

if(strlen($purchaseInvoice['supplier_invoice_number']) <= 0) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_supplier_invoice_number', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_supplier_invoice_number", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_supplier_invoice_number', $class, $id);
}

if(round($purchaseInvoice['payable_amount']) == 0) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_payable_amount', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_payable_amount", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_payable_amount', $class, $id);
}

if(!isset($purchaseInvoice['emission_date']) || $purchaseInvoice['emission_date'] <= 0) {
    $dispatch->dispatch('purchase.accounting.invoice.missing_emission_date', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("missing_emission_date", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.missing_emission_date', $class, $id);
}


$lines_total = 0.0;
foreach($purchaseInvoice['invoice_lines_ids'] as $line_id => $invoiceLine) {
    if(($invoiceLine['owner_share'] + $invoiceLine['tenant_share']) != 100) {
        // error : invalid (non-balanced) owner/tenant ratio
        $dispatch->dispatch('purchase.accounting.invoice.invalid_owner_tenant_ratio', $class, $id, 'important', $script, ['id' => $id]);
        throw new Exception("invalid_owner_tenant_ratio", EQ_ERROR_INVALID_PARAM);
    }
    if(round($invoiceLine['total'] * (1 + $invoiceLine['vat_rate']), 2) != $invoiceLine['price']) {
        // error : Non matching price from vat excl amount & applicable vat rate
        $dispatch->dispatch('purchase.accounting.invoice.non_matching_price', $class, $id, 'important', $script, ['id' => $id]);
        throw new Exception("non_matching_price", EQ_ERROR_INVALID_PARAM);
    }
    $lines_total += $invoiceLine['price'];
}
// symmetrical removal of the alerts (if any)
$dispatch->cancel('purchase.accounting.invoice.invalid_owner_tenant_ratio', $class, $id);
$dispatch->cancel('purchase.accounting.invoice.non_matching_price', $class, $id);

if($purchaseInvoice['price'] != $lines_total || $purchaseInvoice['payable_amount'] != $lines_total) {
    // error : Invoice total and lines total do not match
    $dispatch->dispatch('purchase.accounting.invoice.non_matching_lines_total', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("non_matching_lines_total", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.non_matching_lines_total', $class, $id);
}

$usage_total = 0.0;
$map_fund_accounts = [];
foreach($purchaseInvoice['fund_usage_lines_ids'] as $usage_line_id => $fundUsageLine) {
    if(isset($map_fund_accounts[$fundUsageLine['fund_account_id']])) {
        // error: A same expense account cannot be used twice
        $dispatch->dispatch('purchase.accounting.invoice.duplicate_expense_account', $class, $id, 'important', $script, ['id' => $id]);
        throw new Exception("duplicate_expense_account", EQ_ERROR_INVALID_PARAM);
    }
    $map_fund_accounts[$fundUsageLine['fund_account_id']] = true;
    $usage_total += $fundUsageLine['amount'];
    if(!$fundUsageLine['apportionment_id']) {
        //error: Missing Apportionment (mandatory)
        $dispatch->dispatch('purchase.accounting.invoice.missing_apportionment', $class, $id, 'important', $script, ['id' => $id]);
        throw new Exception("missing_apportionment", EQ_ERROR_INVALID_PARAM);
    }
    if(!$fundUsageLine['expense_account_id']) {
        //error: Missing expense account (mandatory)
        $dispatch->dispatch('purchase.accounting.invoice.missing_expense_account', $class, $id, 'important', $script, ['id' => $id]);
        throw new Exception("missing_expense_account", EQ_ERROR_INVALID_PARAM);
    }
}
// symmetrical removal of the alerts (if any)
$dispatch->cancel('purchase.accounting.invoice.duplicate_expense_account', $class, $id);
$dispatch->cancel('purchase.accounting.invoice.missing_apportionment', $class, $id);
$dispatch->cancel('purchase.accounting.invoice.missing_expense_account', $class, $id);

if($usage_total > $purchaseInvoice['price']) {
    // error: Fund usage cannot exceed invoice total
    $dispatch->dispatch('purchase.accounting.invoice.exceeding_fund_allocation', $class, $id, 'important', $script, ['id' => $id]);
    throw new Exception("exceeding_fund_allocation", EQ_ERROR_INVALID_PARAM);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('purchase.accounting.invoice.exceeding_fund_allocation', $class, $id);
}

/*
#todo - FundUsageLines
    Il faut que le montant du compte de réserve choisi soit suffisant par rapport au montant assigné
    pour le trouver il faut prendre la dernière balance périodique, et ajouter tous les mouvements jusqu'à la date de facture
*/



// #memo - the `alert` field should be forced to be refreshed upon each validation attempt

// a 2xx response mean validation was successful, in all other cases, an Exception is raised
$context->httpResponse()
        ->status(204)
        ->send();
