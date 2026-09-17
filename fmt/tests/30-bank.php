<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\DocumentType;
use documents\processing\DocumentProcess;
use finance\bank\BankStatement;
use finance\bank\BankStatementImport;
use finance\bank\CondominiumBankAccount;
use realestate\property\Condominium;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$prepareBankStatementImport = function ($import_name, $ibans) use ($providers) {
    $condo = Condominium::search(['name', '=', 'ACP HAUTE 115-117'])
        ->read(['id'])
        ->first(true);

    if(!$condo) {
        throw new \Exception('Missing test condominium ACP HAUTE 115-117.');
    }

    /* @var \equal\orm\ObjectManager $orm */
    $orm = $providers['orm'];
    $bank_account_fixtures = [];

    foreach($ibans as $iban) {
        $bankAccount = CondominiumBankAccount::search([
                ['condo_id', '=', $condo['id']],
                ['bank_account_iban', '=', $iban],
                ['is_active', '=', true]
            ])
            ->read(['id'])
            ->first();

        if($bankAccount) {
            $bank_account_fixtures[] = [
                'id'      => $bankAccount['id'],
                'created' => false
            ];
            continue;
        }

        $bank_account_fixtures[] = [
            'id' => $orm->create(CondominiumBankAccount::getType(), [
                'condo_id'          => $condo['id'],
                'object_class'      => CondominiumBankAccount::getType(),
                'description'       => "Bank account fixture for {$import_name}",
                'bank_account_type' => 'bank_current',
                'bank_account_iban' => $iban,
                'is_active'         => true
            ]),
            'created' => true
        ];
    }

    $bankStatementImport = BankStatementImport::create(['name' => $import_name])
        ->read(['id'])
        ->first();

    return [
        'condo_id'                   => $condo['id'],
        'bank_account_fixtures'      => $bank_account_fixtures,
        'bank_statement_import_id'   => $bankStatementImport['id'],
        'expected_process_names'     => [],
        'document_processes'         => [],
        'document_processes_ids'     => []
    ];
};

$runBankStatementImport = function ($data, $fixture_file) {
    $binary = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.$fixture_file);

    BankStatementImport::id($data['bank_statement_import_id'])->update(['data' => $binary]);

    $bankStatementImport = BankStatementImport::id($data['bank_statement_import_id'])
        ->read(['summary', 'logs'])
        ->first(true);
    $data = array_merge(['bank_statement_import' => $bankStatementImport], $data);

    foreach($data['expected_process_names'] as $process_name) {
        $documentProcess = DocumentProcess::search(['name', '=', $process_name])
            ->read([
                'id',
                'name',
                'status',
                'condo_id' => ['id'],
                'document_id' => ['id', 'name'],
                'document_bank_statement_id'
            ])
            ->first(true);

        if($documentProcess) {
            $data['document_processes'][] = $documentProcess;
            $data['document_processes_ids'][] = $documentProcess['id'];
        }
    }

    return $data;
};

$cleanupBankStatementImport = function ($data) use ($providers) {
    if(!is_array($data)) {
        return;
    }

    /* @var \equal\orm\ObjectManager $orm */
    $orm = $providers['orm'];
    $document_processes_ids = $data['document_processes_ids'] ?? [];

    foreach($data['expected_process_names'] ?? [] as $process_name) {
        $documentProcess = DocumentProcess::search(['name', '=', $process_name])
            ->read(['id'])
            ->first(true);

        if($documentProcess) {
            $document_processes_ids[] = $documentProcess['id'];
        }
    }

    foreach(array_unique($document_processes_ids) as $document_process_id) {
        $documentProcess = DocumentProcess::id($document_process_id)
            ->read(['document_id', 'document_bank_statement_id'])
            ->first();

        if(!$documentProcess) {
            continue;
        }

        if($documentProcess['document_bank_statement_id']) {
            $orm->delete(BankStatement::getType(), $documentProcess['document_bank_statement_id'], true);
        }
        if($documentProcess['document_id']) {
            $orm->delete(Document::getType(), $documentProcess['document_id'], true);
        }
        $orm->delete(DocumentProcess::getType(), $document_process_id, true);
    }

    if(!empty($data['bank_statement_import_id'])) {
        $orm->delete(BankStatementImport::getType(), $data['bank_statement_import_id'], true);
    }

    foreach($data['bank_account_fixtures'] ?? [] as $bank_account_fixture) {
        if($bank_account_fixture['created']) {
            $orm->delete(CondominiumBankAccount::getType(), $bank_account_fixture['id'], true);
        }
    }
};

$tests = [

    '3001' => [
        'description' => "Validate CODA import.",
        'help'        => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');
            return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $data]);
        },
        'assert'      => function ($statements) use ($providers) {
            $valid = true;
            foreach($statements as $statement) {
                $data = eQual::run('get', 'json-validate', ['json' => json_encode($statement), 'schema_id' => 'urn:fmt:json-schema:finance:bank-statement']);
                $valid &= $data['result'] ?? false;
            }
            return $valid;
        },
        'rollback'    => function () use ($providers) {
        }
    ],

    '3002' => [
        'description' => "Validate ISABEL XLSX import.",
        'help'        => "Convert a XLSX bank statement to standardized JSON and validate result against `bank-statement` schema.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_isabel.xlsx');
            return eQual::run('get', 'finance_bank_BankStatement_parse-xls', ['data' => base64_encode($data)]);
        },
        'assert'      => function ($statements) use ($providers) {
            $valid = true;

            foreach($statements as $i => $statement) {
                $data = eQual::run('get', 'json-validate', ['json' => json_encode($statement), 'schema_id' => 'urn:fmt:json-schema:finance:bank-statement']);
                $valid &= $data['result'] ?? false;
            }
            return $valid;
        },
        'rollback'    => function () use ($providers) {
        }
    ],

    '3003' => [
        'description' => "Check CODA import.",
        'help'        => "Convert a CODA bank statement to standardized JSON and validate result data.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');
            return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $data]);
        },
        'assert'      => function ($statements) use ($providers) {
            if(count($statements) !== 1) {
                return false;
            }

            $statement = $statements[0];

            $transactions = $statement['transactions'];
            if(count($transactions) !== 5) {
                return false;
            }

            return count($statements) === 1
                && $statement['statement_number'] === '39'
                && $statement['account_iban'] === 'BE88191156749841'
                && $statement['bank_bic'] === 'CREGBEBB'
                && $statement['account_holder'] === 'KALEO - CENTRE BELGE TOURI'
                && $statement['account_type'] === 'current'
                && $statement['opening_balance'] === 11581.24
                && $statement['opening_date'] === '2020-03-21T00:00:00+00:00'
                && $statement['closing_balance'] === 13646.05
                && $statement['closing_date'] === '2011-01-11T00:00:00+00:00'
                && $statement['statement_currency'] === 'EUR'
                && $transactions[0]['amount'] === 19.8
                && $transactions[1]['amount'] === 44.45
                && $transactions[2]['amount'] === -479.04
                && $transactions[3]['amount'] === -479.04
                && $transactions[4]['amount'] === 63.74;
        },
        'rollback'    => function () use ($providers) {
        },
    ],

    '3004' => [
        'description' => "Test document processing of CODA document.",
        'help'        => "Create BankStatementImport with coda txt to test the creation of the DocumentProcess.",
        'arrange'     => function () use ($prepareBankStatementImport) {
            $data = $prepareBankStatementImport(
                'test_3004_doc_processing_coda_document.cod',
                ['BE88191156749841']
            );
            $data['expected_process_names'] = ['test_3004_doc_processing_coda_document(1).xlsx'];

            return $data;
        },
        'act'         => function ($data) use ($runBankStatementImport) {
            return $runBankStatementImport($data, 'bank_coda.txt');
        },
        'assert'      => function ($data) use ($providers) {
            if(count($data['document_processes']) !== 1) {
                return false;
            }

            $documentProcess = $data['document_processes'][0];

            return strpos($data['bank_statement_import']['summary'], 'Statements imported: 1') !== false
                && $documentProcess['name'] === 'test_3004_doc_processing_coda_document(1).xlsx'
                && $documentProcess['status'] === 'assigned'
                && $documentProcess['document_id']
                && $documentProcess['document_id']['name'] === 'test_3004_doc_processing_coda_document(1).xlsx';
        },
        'rollback'    => function ($data) use ($cleanupBankStatementImport) {
            $cleanupBankStatementImport($data);
        }
    ],

    '3005' => [
        'description' => "Test automatic document assignment during CODA processing.",
        'help'        => "Create a BankStatementImport with a known bank account and verify automatic condominium assignment.",
        'arrange'     => function () use ($prepareBankStatementImport) {
            $data = $prepareBankStatementImport(
                'test_3005_doc_auto_assignment_coda_document.cod',
                ['BE88191156749841']
            );
            $data['expected_process_names'] = ['test_3005_doc_auto_assignment_coda_document(1).xlsx'];

            return $data;
        },
        'act'         => function ($data) use ($runBankStatementImport) {
            return $runBankStatementImport($data, 'bank_coda.txt');
        },
        'assert'      => function ($data) use ($providers) {
            if(count($data['document_processes']) !== 1) {
                return false;
            }

            $documentProcess = $data['document_processes'][0];

            return strpos($data['bank_statement_import']['summary'], 'Statements imported: 1') !== false
                && $documentProcess['status'] === 'assigned'
                && $documentProcess['condo_id']
                && $documentProcess['condo_id']['id'] === $data['condo_id'];
        },
        'rollback'    => function ($data) use ($cleanupBankStatementImport) {
            $cleanupBankStatementImport($data);
        }
    ],

    '3006' => [
        'description' => "Test explicit document assignment transition.",
        'help'        => "Create a DocumentProcess, set its condominium without events and use the assign transition.",
        'arrange'     => function () use ($providers) {
            $condo = Condominium::search(['name', '=', 'ACP HAUTE 115-117'])
                ->read(['id'])
                ->first(true);

            $documentType = DocumentType::search(['code', '=', 'bank_statement'])
                ->read(['id'])
                ->first();

            $documentProcess = DocumentProcess::create([
                    'name'             => 'test_3006_explicit_document_assignment.xlsx',
                    'document_type_id' => $documentType['id']
                ])
                ->read(['id'])
                ->first();

            return [
                'condo_id'           => $condo['id'],
                'document_process_id' => $documentProcess['id']
            ];
        },
        'act'         => function ($data) use ($providers) {
            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            // Update condo_id without triggering assign
            $events = $orm->disableEvents();
            try {
                $orm->update(
                    DocumentProcess::getType(),
                    $data['document_process_id'],
                    ['condo_id' => $data['condo_id']]
                );
            }
            finally {
                $orm->enableEvents($events);
            }

            DocumentProcess::id($data['document_process_id'])->transition('assign');

            return $data;
        },
        'assert'      => function ($data) use ($providers) {
            $documentProcess = DocumentProcess::id($data['document_process_id'])
                ->read(['status', 'condo_id'])
                ->first();

            return $documentProcess['status'] === 'assigned'
                && $documentProcess['condo_id'] === $data['condo_id'];
        },
        'rollback'    => function ($data) use ($providers) {
            if(!is_array($data) || empty($data['document_process_id'])) {
                return;
            }
            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];
            $orm->delete(DocumentProcess::getType(), $data['document_process_id'], true);
        }
    ],

    '3007' => [
        'description' => "Test document processing of CODA documents.",
        'help'        => "Create BankStatementImport with coda txt to test the creation of two DocumentProcess.",
        'arrange'     => function () use ($prepareBankStatementImport) {
            $data = $prepareBankStatementImport(
                'test_3007_doc_processing_coda_documents.cod',
                ['BE88191156749841', 'BE53123456789012']
            );
            $data['expected_process_names'] = [
                'test_3007_doc_processing_coda_documents(1).xlsx',
                'test_3007_doc_processing_coda_documents(2).xlsx'
            ];

            return $data;
        },
        'act'         => function ($data) use ($runBankStatementImport) {
            return $runBankStatementImport($data, 'bank_coda_multi_accounts.txt');
        },
        'assert'      => function ($data) use ($providers) {
            if(count($data['document_processes']) !== 2) {
                return false;
            }

            foreach($data['document_processes'] as $documentProcess) {
                if(
                    $documentProcess['status'] !== 'assigned'
                    || !$documentProcess['document_id']
                    || $documentProcess['document_id']['name'] !== $documentProcess['name']
                ) {
                    return false;
                }
            }

            return strpos($data['bank_statement_import']['summary'], 'Statements imported: 2') !== false;
        },
        'rollback'    => function ($data) use ($cleanupBankStatementImport) {
            $cleanupBankStatementImport($data);
        }
    ]

];

