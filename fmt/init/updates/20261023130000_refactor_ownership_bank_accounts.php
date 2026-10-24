<?php

use equal\db\DBConnector;
use finance\bank\OwnershipBankAccount;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;

$db = DBConnector::getInstance()->connect();

$source_table = 'finance_bank_bankaccount';
$target_table = 'finance_bank_ownershipbankaccount';
$money_refund_table = 'finance_accounting_miscoperation';

$tables = array_fill_keys($db->getTables(), true);
foreach([$source_table, $target_table] as $required_table) {
    if(!isset($tables[$required_table])) {
        throw new Exception("missing_table:{$required_table}", EQ_ERROR_INVALID_CONFIG);
    }
}

$required_source_columns = [
    'id',
    'model',
    'object_class',
    'condo_id',
    'ownership_id',
    'owner_identity_id',
    'is_primary',
    'is_validated'
];
$source_columns = $db->getTableColumns($source_table);
$missing_source_columns = array_values(array_diff($required_source_columns, $source_columns));
if(count($missing_source_columns)) {
    throw new Exception(
        'missing_columns:' . implode(',', $missing_source_columns),
        EQ_ERROR_INVALID_CONFIG
    );
}

$ownership_bank_account_rows = [];
$result = $db->getRecords(
    $source_table,
    $required_source_columns,
    null,
    [
        [['object_class', '=', 'finance\\bank\\OwnershipBankAccount']],
        [['model', '=', 'finance\\bank\\OwnershipBankAccount']],
        [['ownership_id', 'is not', null]]
    ]
);
while($row = $db->fetchArray($result)) {
    $ownership_bank_account_rows[(int) $row['id']] = $row;
}

if(!count($ownership_bank_account_rows)) {
    return;
}

$missing_owner_identity_ids = [];
$invalid_owner_identity_ids = [];

foreach($ownership_bank_account_rows as $bank_account_id => $row) {
    $owner_identity_id = (int) ($row['owner_identity_id'] ?? 0);
    if(!$owner_identity_id) {
        $missing_owner_identity_ids[] = $bank_account_id;
        continue;
    }

    $ownership_id = (int) ($row['ownership_id'] ?? 0);
    if(!$ownership_id) {
        $invalid_owner_identity_ids[] = $bank_account_id;
        continue;
    }

    $is_owner = Owner::search([
            ['ownership_id', '=', $ownership_id],
            ['identity_id', '=', $owner_identity_id]
        ])
        ->count() > 0;

    $ownership = Ownership::id($ownership_id)
        ->read(['condo_id', 'has_external_representative', 'representative_identity_id'])
        ->first();
    $is_external_representative = $ownership
        && $ownership['has_external_representative']
        && $ownership['representative_identity_id'] === $owner_identity_id;

    if(!$is_owner && !$is_external_representative) {
        $invalid_owner_identity_ids[] = $bank_account_id;
    }
}

if(count($invalid_owner_identity_ids)) {
    throw new Exception(
        'invalid_ownership_bank_account_owner_identity:' . implode(',', $invalid_owner_identity_ids),
        EQ_ERROR_INVALID_CONFIG
    );
}

$db->sendQuery('START TRANSACTION');
try {
    foreach($ownership_bank_account_rows as $bank_account_id => $row) {
        if(in_array($bank_account_id, $missing_owner_identity_ids, true)) {
            continue;
        }

        $ownership = Ownership::id((int) $row['ownership_id'])
            ->read(['condo_id'])
            ->first();
        $condo_id = (int) ($row['condo_id'] ?: ($ownership['condo_id'] ?? 0));
        if(!$condo_id) {
            throw new Exception(
                "ownership_bank_account_missing_condo:{$bank_account_id}",
                EQ_ERROR_INVALID_CONFIG
            );
        }

        // Retype first so the new link can resolve this record as a regular BankAccount.
        // The whole update remains transactional if link creation fails.
        $db->setRecords(
            $source_table,
            [$bank_account_id],
            [
                'model'        => 'finance\\bank\\BankAccount',
                'object_class' => 'finance\\bank\\BankAccount'
            ]
        );

        $ownershipBankAccount = OwnershipBankAccount::search([
                ['condo_id', '=', $condo_id],
                ['ownership_id', '=', (int) $row['ownership_id']],
                ['bank_account_id', '=', $bank_account_id]
            ])
            ->first();

        if(!$ownershipBankAccount) {
            $ownershipBankAccount = OwnershipBankAccount::create([
                    'condo_id'          => $condo_id,
                    'ownership_id'      => (int) $row['ownership_id'],
                    'owner_identity_id' => (int) $row['owner_identity_id'],
                    'bank_account_id'   => $bank_account_id,
                    'is_primary'        => (bool) $row['is_primary'],
                    'is_validated'      => (bool) $row['is_validated']
                ])
                ->first();
        }

        if(!$ownershipBankAccount) {
            throw new Exception(
                "ownership_bank_account_migration_failed:{$bank_account_id}",
                EQ_ERROR_UNKNOWN
            );
        }

        if(isset($tables[$money_refund_table])) {
            $db->setRecords(
                $money_refund_table,
                null,
                ['ownership_bank_account_id' => $ownershipBankAccount['id']],
                [[['ownership_bank_account_id', '=', $bank_account_id]]]
            );
        }
    }

    if(count($missing_owner_identity_ids)) {
        if(isset($tables[$money_refund_table])) {
            $db->setRecords(
                $money_refund_table,
                null,
                ['ownership_bank_account_id' => null],
                [[['ownership_bank_account_id', 'in', $missing_owner_identity_ids]]]
            );
        }

        if(isset($tables['sale_pay_funding'])) {
            foreach(['bank_account_id', 'counterpart_bank_account_id'] as $field) {
                $db->setRecords(
                    'sale_pay_funding',
                    null,
                    [$field => null],
                    [[[$field, 'in', $missing_owner_identity_ids]]]
                );
            }
        }

        if(isset($tables['sale_pay_payment'])) {
            $db->setRecords(
                'sale_pay_payment',
                null,
                ['receipt_bank_account_id' => null],
                [[['receipt_bank_account_id', 'in', $missing_owner_identity_ids]]]
            );
        }

        $db->deleteRecords($source_table, $missing_owner_identity_ids);
    }

    $remaining = $db->getRecords(
        $source_table,
        ['id'],
        null,
        [
            [['object_class', '=', 'finance\\bank\\OwnershipBankAccount']],
            [['model', '=', 'finance\\bank\\OwnershipBankAccount']]
        ]
    );
    if($db->fetchArray($remaining)) {
        throw new Exception('ownership_bank_account_migration_incomplete', EQ_ERROR_UNKNOWN);
    }

    $db->sendQuery('COMMIT');
}
catch(Throwable $e) {
    $db->sendQuery('ROLLBACK');
    throw $e;
}
