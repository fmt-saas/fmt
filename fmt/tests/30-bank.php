<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\Document;
use documents\processing\DocumentProcess;
use finance\bank\BankStatementImport;
use realestate\property\Condominium;

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

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
        'help'        => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
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
        'arrange'     => function () use ($providers) {
            $bankStatementImport = BankStatementImport::create([
                'name' => 'Test document processing of CODA document'
            ])
                ->read(['id'])
                ->first();

            return $bankStatementImport;
        },
        'act'         => function ($bankStatementImport) use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');

            BankStatementImport::id($bankStatementImport['id'])->update(['data' => $data]);

            return $bankStatementImport;
        },
        'assert'      => function ($bankStatementImport) use ($providers) {
            $bankStatementDocument = Document::search(['name', '=', 'Test document processing of CODA document'])
                ->read(['name'])
                ->first();

            $xlsxDocuments = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->get();

            $document_processes_ids = [];
            foreach($xlsxDocuments as $xlsxDocument) {
                $document_processes_ids[] = $xlsxDocument['document_process_id'];
            }

            $documentProcesses = DocumentProcess::ids($document_processes_ids)
                ->read(['name', 'status', 'document_origin_code', 'document_origin'])
                ->get(true);

            return $bankStatementDocument['name'] === 'Test document processing of CODA document'
                && count($xlsxDocuments) === 1
                && count($documentProcesses) === 1 && $documentProcesses[0]['status'] === 'created';
        },
        'rollback'    => function () use ($providers) {
            $bankStatementDocument = Document::search(['name', '=', 'Test document processing of CODA document'])
                ->read(['id'])
                ->first();

            $accountsBankStatementXlsxDocuments = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->get();

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            $orm->delete(Document::getType(), $bankStatementDocument['id'], true);

            foreach($accountsBankStatementXlsxDocuments as $id => $accountsBankStatementXlsxDocument) {
                $orm->delete(Document::getType(), $id, true);
                $orm->delete(DocumentProcess::getType(), $accountsBankStatementXlsxDocument['document_process_id'], true);
            }
        }
    ],

    '3005' => [
        'description' => "Test document assignment during a processing of CODA document.",
        'help'        => "Create BankStatementImport with coda txt and assign a condo id to the document process to test assignment.",
        'arrange'     => function () use ($providers) {
            $condo = Condominium::create(['name' => 'Test condo'])
                ->first(true);

            $bankStatementImport = BankStatementImport::create([
                'name' => 'Test document assignment during processing of CODA document'
            ])
                ->read(['id'])
                ->first();

            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');

            BankStatementImport::id($bankStatementImport['id'])->update(['data' => $data]);

            $bankStatementDocument = Document::search(['name', '=', 'Test document assignment during processing of CODA document'])
                ->read(['name'])
                ->first();

            $xlsxDocument = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->first();

            $documentProcess = DocumentProcess::id($xlsxDocument['document_process_id'])
                ->read(['id'])
                ->first();

            return compact('condo', 'documentProcess');
        },
        'act'         => function ($data) use ($providers) {
            ['condo' => $condo, 'documentProcess' => $documentProcess] = $data;

            // Update condo_id to trigger assign transition in onafterupdate
            DocumentProcess::id($documentProcess['id'])->update(['condo_id' => $condo['id']]);

            return $documentProcess['id'];
        },
        'assert'      => function ($document_process_id) use ($providers) {
            $documentProcess = DocumentProcess::id($document_process_id)
                ->read(['status'])
                ->first();

            return $documentProcess['status'] === 'assigned';
        },
        'rollback'    => function () use ($providers) {
            $condo = Condominium::search(['name', '=', 'Test condo'])
                ->read(['id'])
                ->first();

            $bankStatementDocument = Document::search(['name', '=', 'Test document assignment during processing of CODA document'])
                 ->read(['id'])
                 ->first();

            $accountsBankStatementXlsxDocuments = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                  ->read(['document_process_id'])
                  ->get();

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            $orm->delete(Condominium::getType(), $condo['id'], true);

            $orm->delete(Document::getType(), $bankStatementDocument['id'], true);

            foreach($accountsBankStatementXlsxDocuments as $id => $accountsBankStatementXlsxDocument) {
                $orm->delete(Document::getType(), $id, true);
                $orm->delete(DocumentProcess::getType(), $accountsBankStatementXlsxDocument['document_process_id'], true);
            }
        }
    ],

    '3006' => [
        'description' => "Test document assignment during a processing of CODA document.",
        'help'        => "Create BankStatementImport with coda txt and use assign transition to test assignment.",
        'arrange'     => function () use ($providers) {
            $condo = Condominium::create(['name' => 'Test condo'])
                ->first(true);

            $bankStatementImport = BankStatementImport::create([
                'name' => 'Test document assignment during processing of CODA document'
            ])
                ->read(['id'])
                ->first();

            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');

            BankStatementImport::id($bankStatementImport['id'])->update(['data' => $data]);

            $bankStatementDocument = Document::search(['name', '=', 'Test document assignment during processing of CODA document'])
                ->read(['name'])
                ->first();

            $xlsxDocument = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->first();

            $documentProcess = DocumentProcess::id($xlsxDocument['document_process_id'])
                ->read(['id'])
                ->first();

            return compact('condo', 'documentProcess');
        },
        'act'         => function ($data) use ($providers) {
            ['condo' => $condo, 'documentProcess' => $documentProcess] = $data;

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            // Update condo_id without triggering assign
            $events = $orm->disableEvents();
            $orm->update(DocumentProcess::getType(), $documentProcess['id'], ['condo_id' => $condo['id']]);
            $orm->enableEvents($events);

            DocumentProcess::id($documentProcess['id'])->transition('assign');

            return $documentProcess['id'];
        },
        'assert'      => function ($document_process_id) use ($providers) {
            $documentProcess = DocumentProcess::id($document_process_id)
                ->read(['status'])
                ->first();

            return $documentProcess['status'] === 'assigned';
        },
        'rollback'    => function () use ($providers) {
            $condo = Condominium::search(['name', '=', 'Test condo'])
                ->read(['id'])
                ->first();

            $bankStatementDocument = Document::search(['name', '=', 'Test document assignment during processing of CODA document'])
                ->read(['id'])
                ->first();

            $accountsBankStatementXlsxDocuments = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->get();

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            $orm->delete(Condominium::getType(), $condo['id'], true);

            $orm->delete(Document::getType(), $bankStatementDocument['id'], true);

            foreach($accountsBankStatementXlsxDocuments as $id => $accountsBankStatementXlsxDocument) {
                $orm->delete(Document::getType(), $id, true);
                $orm->delete(DocumentProcess::getType(), $accountsBankStatementXlsxDocument['document_process_id'], true);
            }
        }
    ],

    '3007' => [
        'description' => "Test document processing of CODA documents.",
        'help'        => "Create BankStatementImport with coda txt to test the creation of two DocumentProcess.",
        'arrange'     => function () use ($providers) {
            $bankStatementImport = BankStatementImport::create([
                'name' => 'Test document processing of CODA documents'
            ])
                ->read(['id'])
                ->first();

            return $bankStatementImport;
        },
        'act'         => function ($bankStatementImport) use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda_multi_accounts.txt');

            BankStatementImport::id($bankStatementImport['id'])->update(['data' => $data]);

            return $bankStatementImport;
        },
        'assert'      => function ($bankStatementImport) use ($providers) {
            $bankStatementDocument = Document::search(['name', '=', 'Test document processing of CODA documents'])
                ->read(['name'])
                ->first();

            $xlsxDocuments = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->get();

            $document_processes_ids = [];
            foreach($xlsxDocuments as $xlsxDocument) {
                $document_processes_ids[] = $xlsxDocument['document_process_id'];
            }

            $documentProcesses = DocumentProcess::ids($document_processes_ids)
                ->read(['name', 'status', 'document_origin_code', 'document_origin'])
                ->get(true);

            return $bankStatementDocument['name'] === 'Test document processing of CODA documents'
                && count($xlsxDocuments) === 2
                && count($documentProcesses) === 2 && $documentProcesses[0]['status'] === 'created' && $documentProcesses[1]['status'] === 'created';
        },
        'rollback'    => function () use ($providers) {
            $bankStatementDocument = Document::search(['name', '=', 'Test document processing of CODA documents'])
                ->read(['id'])
                ->first();

            $accountsBankStatementXlsxDocuments = Document::search(['origin_document_id', '=', $bankStatementDocument['id']])
                ->read(['document_process_id'])
                ->get();

            /* @var \equal\orm\ObjectManager $orm */
            $orm = $providers['orm'];

            $orm->delete(Document::getType(), $bankStatementDocument['id'], true);

            foreach($accountsBankStatementXlsxDocuments as $id => $accountsBankStatementXlsxDocument) {
                $orm->delete(Document::getType(), $id, true);
                $orm->delete(DocumentProcess::getType(), $accountsBankStatementXlsxDocument['document_process_id'], true);
            }
        }
    ]

];

