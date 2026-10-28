<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\BankAccount;
use finance\bank\BankStatementLine;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Add the counterparty IBAN of a bank statement line to an ownership for a selected owner.',
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'description'       => 'Bank statement line whose counterparty IBAN must be added.',
            'foreign_object'    => 'finance\bank\BankStatementLine',
            'required'          => true
        ],
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => 'Ownership targeted by the bank statement line.',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],
        'owner_id' => [
            'type'              => 'many2one',
            'description'       => 'Owner to associate with the counterparty bank account.',
            'foreign_object'    => 'realestate\ownership\Owner',
            'domain'            => ['ownership_id', '=', 'object.ownership_id'],
            'default'           => function ($id = null) {
                if(!$id) {
                    return null;
                }

                $bankStatementLine = BankStatementLine::id($id)
                    ->read(['ownership_id', 'account_iban'])
                    ->first();

                if(!$bankStatementLine || !$bankStatementLine['ownership_id'] || !$bankStatementLine['account_iban']) {
                    return null;
                }

                $iban = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $bankStatementLine['account_iban']));
                if(!$iban) {
                    return null;
                }

                $owners = Owner::search([
                        ['ownership_id', '=', $bankStatementLine['ownership_id']],
                        ['identity_id', '<>', null]
                    ])
                    ->read(['identity_id', 'bank_account_iban']);

                $owner_ids_by_identity_id = [];
                $matching_owner_ids = [];
                foreach($owners as $owner_id => $owner) {
                    $owner_ids_by_identity_id[$owner['identity_id']][] = $owner_id;

                    $owner_iban = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $owner['bank_account_iban']));
                    if($owner_iban === $iban) {
                        $matching_owner_ids[$owner_id] = true;
                    }
                }

                if(!empty($owner_ids_by_identity_id)) {
                    $bankAccounts = BankAccount::search([
                            ['owner_identity_id', 'in', array_keys($owner_ids_by_identity_id)],
                            ['bank_account_iban', '=', $iban]
                        ])
                        ->read(['owner_identity_id']);

                    foreach($bankAccounts as $bankAccount) {
                        $owner_ids = $owner_ids_by_identity_id[$bankAccount['owner_identity_id']] ?? [];
                        foreach($owner_ids as $owner_id) {
                            $matching_owner_ids[$owner_id] = true;
                        }
                    }
                }

                return count($matching_owner_ids) === 1
                    ? (int) array_keys($matching_owner_ids)[0]
                    : null;
            },
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$bankStatementLine = BankStatementLine::id($params['id'])
    ->read(['ownership_id'])
    ->first();

if(!$bankStatementLine) {
    throw new Exception('unknown_bank_statement_line', EQ_ERROR_UNKNOWN_OBJECT);
}

if($bankStatementLine['ownership_id'] !== $params['ownership_id']) {
    throw new Exception('invalid_ownership', EQ_ERROR_INVALID_PARAM);
}

$owner = Owner::id($params['owner_id'])
    ->read(['ownership_id', 'identity_id'])
    ->first();

if(
    !$owner
    || !$owner['identity_id']
    || $owner['ownership_id'] !== $bankStatementLine['ownership_id']
) {
    throw new Exception('invalid_owner', EQ_ERROR_INVALID_PARAM);
}

BankStatementLine::id($params['id'])
    ->do('add_ownership_bank_account', ['owner_id' => $params['owner_id']]);

$context->httpResponse()
    ->status(204)
    ->send();
