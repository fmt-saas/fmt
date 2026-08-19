<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\BankStatement;

[$params, $providers] = eQual::announce([
    'description'   => 'Hard-delete pending bank statements.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'description'       => 'The bank statement to delete.',
            'foreign_object'    => 'finance\bank\BankStatement'
        ],
        'ids' => [
            'type'              => 'one2many',
            'description'       => 'List of bank statements to delete.',
            'foreign_object'    => 'finance\bank\BankStatement',
            'default'           => []
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$ids = $params['ids'];

if(isset($params['id']) && $params['id'] > 0) {
    $ids[] = $params['id'];
}

if(empty($ids)) {
    throw new Exception('no_bank_statement_selected', EQ_ERROR_INVALID_PARAM);
}

$bankStatements = BankStatement::ids($ids)
    ->read(['status']);

foreach($bankStatements as $bankStatement) {
    if($bankStatement['status'] !== 'pending') {
        throw new Exception('non_pending_bank_statement', EQ_ERROR_INVALID_PARAM);
    }
}

$bankStatements->delete(true);

$context->httpResponse()
    ->status(204)
    ->send();
