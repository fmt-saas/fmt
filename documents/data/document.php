<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;
use finance\bank\BankStatement;
use identity\User;
use realestate\funding\ExpenseStatement;
use realestate\funding\FundRequestExecution;
use realestate\ownership\Owner;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given identifier.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the document.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
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
    'providers'     => ['context', 'orm', 'auth', 'adapt']
]);

['context' => $context, 'orm' => $om, 'auth' => $auth, 'adapt' => $adapt] = $providers;

$user_id = $auth->userId();

$document = Document::id($params['id'])
    ->read([
        'document_visibility', 'condo_id', 'ownership_id', 'name', 'data', 'content_type',
        'purchase_invoice_id', 'expense_statement_id', 'fund_request_execution_id', 'bank_statement_id'
    ])
    ->first();

$content_type = $document['content_type'];
$filename = $document['name'];
$output = $document['data'];

// #todo - restore - make sure to test with user relating to employee, and add extra rights for admins & ROOT users
/*
// check visibility rules
switch($document['document_visibility']) {
    case 'public':
        // visible to all condo owners + syndic
        // make sure user relates to condo_id of the document
        $user = User::id($user_id)->read(['identity_id' => ['employee_id']])->first();

        if(!($user['identity_id']['employee_id'] ?? null)) {
            $owners = Owner::search(['identity_id', '=', $user['identity_id']['id']])->read(['condo_id']);
            $found = false;
            foreach($owners as $owner_id => $owner) {
                if($owner['condo_id'] === $document['condo_id']) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
            }
        }
        break;
    case 'protected':
        // visible only to syndic
        // user must be linked to an employee
        $user = User::id($user_id)->read(['employee_id'])->first();

        if(!$user || !($user['employee_id'] ?? null)) {
            throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
        }
        break;
    case 'private':
        // visible only a single owner (to which the document is linked) + syndic
        // make sure the user relates to the ownership_id of the document
        $user = User::id($user_id)->read(['identity_id' => ['employee_id']])->first();

        if(!($user['identity_id']['employee_id'] ?? null)) {
            $owners = Owner::search(['identity_id', '=', $user['identity_id']['id']])->read(['ownership_id']);
            $found = false;
            foreach($owners as $owner_id => $owner) {
                if($owner['ownership_id'] === $document['ownership_id']) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
            }
        }
        break;
}
*/


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
        $doc_info[] = date('Y-m-d', $expenseStatement['posting_date']);
        $doc_info[] = $expenseStatement['invoice_number'];
    }
}
elseif($document['fund_request_execution_id']) {
    $fundRequestExecution = FundRequestExecution::id($document['fund_request_execution_id'])->read(['status', 'invoice_number', 'posting_date'])->first();
    if($fundRequestExecution['status'] === 'posted') {
        $doc_info[] = date('Y-m-d', $fundRequestExecution['posting_date']);
        $doc_info[] = $fundRequestExecution['invoice_number'];
    }
}
elseif($document['bank_statement_id']) {
    $bankStatement = BankStatement::id($document['bank_statement_id'])->read(['status', 'name', 'date'])->first();
    $doc_info[] = date('Y-m-d', $bankStatement['date']);
    $doc_info[] = $bankStatement['name'];
}

if(count($doc_info)) {
    $output = eQual::run('get', 'documents_Document_add-overlay', ['id' => $params['id'], 'overlay_text' => implode(' | ', $doc_info)]);
}

$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'] . '; filename="' . $filename . '"')
        ->header('Content-Type', $content_type)
        ->body($output, true)
        ->send();
