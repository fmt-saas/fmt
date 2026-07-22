<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;
use finance\bank\BankStatement;
use fmt\access\DocumentAccessHelper;
use realestate\funding\ExpenseStatement;
use realestate\funding\FundRequestExecution;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given identifier.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier (hash) of the document.',
            'type'              => 'string',
            'required'          => true
        ],
        'disposition' => [
            'type'          => 'string',
            'selection'     => [
                'inline',
                'attachment'
            ],
            'default'       => 'inline'
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/octet-stream'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'],
    'providers'     => ['context', 'orm', 'auth', 'adapt', 'access']
]);

/**
 * @var \equal\access\AccessController $access
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth, 'adapt' => $adapt, 'access' => $access] = $providers;

$user_id = $auth->userId();

$documents_ids = $orm->search(Document::getType(), ['hash', '=', $params['id']]);

if(!is_array($documents_ids) || !count($documents_ids)) {
    throw new Exception('unknown_document', EQ_ERROR_UNKNOWN_OBJECT);
}

$document_id = current($documents_ids);

$documentAccessHelper = new DocumentAccessHelper();

if(!$documentAccessHelper->userCanReadObjects($orm, $access, Document::getType(), [$document_id], $user_id)) {
    throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
}

$document = Document::id($document_id)
    ->read([
        'id', 'name', 'data', 'content_type',
        'purchase_invoice_id', 'expense_statement_id', 'fund_request_execution_id', 'bank_statement_id'
    ])
    ->first();

if(!$document) {
    throw new Exception('unknown_document', EQ_ERROR_UNKNOWN_OBJECT);
}

$content_type = $document['content_type'];
$filename = $document['name'];
$output = $document['data'];

// for accounting documents, relay to `add-overlay` to force output with additional information
$doc_info = [];

if($document['purchase_invoice_id']) {
    $purchaseInvoice = PurchaseInvoice::id($document['purchase_invoice_id'])->read(['status', 'invoice_number', 'posting_date'])->first();
    if($purchaseInvoice['status'] === 'posted') {
        $doc_info[] = date('Y-m-d', $purchaseInvoice['posting_date']);
        $doc_info[] = $purchaseInvoice['invoice_number'];
    }
}
elseif($document['expense_statement_id']) {
    $expenseStatement = ExpenseStatement::id($document['expense_statement_id'])->read(['status', 'invoice_number', 'posting_date'])->first();
    if($expenseStatement['status'] === 'posted') {
        /*
        $doc_info[] = date('Y-m-d', $expenseStatement['posting_date']);
        $doc_info[] = $expenseStatement['invoice_number'];
        */
    }
}
elseif($document['fund_request_execution_id']) {
    $fundRequestExecution = FundRequestExecution::id($document['fund_request_execution_id'])->read(['status', 'invoice_number', 'posting_date'])->first();
    if($fundRequestExecution['status'] === 'posted') {
        /*
        $doc_info[] = date('Y-m-d', $fundRequestExecution['posting_date']);
        $doc_info[] = $fundRequestExecution['invoice_number'];
        */
    }
}
elseif($document['bank_statement_id']) {
    $bankStatement = BankStatement::id($document['bank_statement_id'])->read(['status', 'name', 'date'])->first();
    $doc_info[] = date('Y-m-d', $bankStatement['date']);
    $doc_info[] = $bankStatement['name'];
}

if(count($doc_info)) {
    $output = eQual::run('get', 'documents_Document_add-overlay', ['id' => $document['id'], 'resize' => 0.9, 'overlay_text' => implode(' | ', $doc_info)]);
}

$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'] . '; filename="' . $filename . '"')
        ->header('Content-Type', $content_type)
        ->body($output, true)
        ->send();
