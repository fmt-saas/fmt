<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\processing\DocumentProcess;
use equal\orm\Domain;
use finance\bank\BankStatement;
use realestate\purchase\accounting\invoice\PurchaseInvoice;

[$params, $providers] = eQual::announce([
    'description'   => 'Lists all processings .',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'employee_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'hr\employee\Employee',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required."
        ],

        'domain' => [
            'description'   => 'Criterias that results have to match (serie of conjunctions)',
            'type'          => 'array',
            'default'       => []
        ],

        /* parameters used as properties of virtual entity */

        'date_last' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'document_type_code' => [
            'type'              => 'string',
            'selection'         => [
                'document',
                'invoice',
                'bank_statement'
            ],
            'description'       => 'Code identifier of the document type.',
            'help'              => 'The document type code is used for identifying the type of processing (invoice, bank_statement, maintenance_report, contract, etc.'
        ],
        'count' => [
            'type'              => 'integer',
            'description'       => 'Total revenue from meals.'
        ],
        'count_alerts' => [
            'type'              => 'integer',
            'description'       => 'Total revenue from meals.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;


// build final result
$result = [];


$employee_id = $params['employee_id'] ?? null;

// look for a given employee_id in the domain, if not explicitly provided
if(count($params['domain'])) {
    $tmpDomain = new Domain($params['domain']);
    foreach($tmpDomain->getClauses() as $clause) {
        foreach($clause->getConditions() as $condition) {
            if($condition->getOperand() === 'assigned_employee_id' && in_array($condition->getOperator(), ['=', 'in'], true)) {
                $employee_id = $condition->getValue();
                break 2;
            }
        }
    }
}


// #todo - restrict by status matching employee's roles
// retrouver les roles de l'employé


// 1) special case for DocumentProcess

// if the employee has the role `document_dispatch_officer`, assume they are assigned to all documents with status `created` and not yet `assigned`
if($employee_id) {
    $documentProcesses = DocumentProcess::search([['assigned_employee_id', '=', $employee_id], ['status', '=', 'created']])
        ->read(['id', 'created']);

    if($documentProcesses->count() > 0) {
        $count = 0;
        $alerts = 0;
        $date_last = 0;
        foreach($documentProcesses as $documentProcess) {
            ++$count;
            if($documentProcess['created'] > $date_last) {
                $date_last = $documentProcess['created'];
            }
            /*
            if($documentProcess['alert'] && !in_array($documentProcess['alert'], ['info', 'success'], true)) {
                ++$alerts;
            }
            */
        }

        $result[] = [
            'document_type_code'    => 'document',
            'count'                 => $count,
            'count_alerts'          => $alerts,
            'date_last'             => date('c', $date_last)
        ];
    }
}

// 2) retrieve and merge all results for each document type

$domain = [
    ['document_process_status', '<>', 'integrated']
];

if($employee_id) {
    $domain[] = ['assigned_employee_id', '=', $employee_id];
}


$purchaseInvoices = PurchaseInvoice::search($domain)->read(['id', 'alert', 'created']);

if($purchaseInvoices->count() > 0) {
    $count = 0;
    $alerts = 0;
    $date_last = 0;
    foreach($purchaseInvoices as $purchaseInvoice) {
        ++$count;
        if($purchaseInvoice['created'] > $date_last) {
            $date_last = $purchaseInvoice['created'];
        }
        if($purchaseInvoice['alert'] && !in_array($purchaseInvoice['alert'], ['info', 'success'], true)) {
            ++$alerts;
        }
    }

    $result[] = [
        'document_type_code'    => 'invoice',
        'count'                 => $count,
        'count_alerts'          => $alerts,
        'date_last'             => date('c', $date_last)
    ];
}

$bankStatements = BankStatement::search($domain)->read(['id', 'alert', 'created']);

if($bankStatements->count() > 0) {
    $count = 0;
    $alerts = 0;
    $date_last = 0;
    foreach($bankStatements as $bankStatement) {
        ++$count;
        if($bankStatement['created'] > $date_last) {
            $date_last = $bankStatement['created'];
        }
        if($bankStatement['alert'] && $bankStatement['alert'] !== 'info') {
            ++$alerts;
        }
    }

    $result[] = [
        'document_type_code'    => 'bank_statement',
        'count'                 => $count,
        'count_alerts'          => $alerts,
        'date_last'             => date('c', $date_last)
    ];
}


$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();
