<?php

use documents\Document;
use finance\bank\Bank;
use fmt\import\DataImport;
use identity\Identity;
use purchase\supplier\Supplier;

$tests = [
    '0101' => [
        'description'       => "Tests that check data import works for suppliers_import.",
        'help'              => "
            Creates a document and a data import for suppliers with suppliers_import.xls file.
            Triggers action fmt_import_DataImport_check on data import.
            Assert that the check was successful.
            Removes the created data import and document.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'suppliers_import.xlsx');

            $document = Document::create([
                'name' => 'Suppliers import test 1'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Suppliers import test 1',
                'document_id'   => $document['id'],
                'import_type'   => 'suppliers_import'
            ])
                ->read(['id'])
                ->first();
        },
        'act'               => function($data_import) {
            return eQual::run('do', 'fmt_import_DataImport_check', ['id' => $data_import['id']])['result'];
        },
        'assert'            => function($check_results) {
            return $check_results['errors'] === 0;
        },
        'rollback'          => function() {
            DataImport::search(['name', '=', 'Suppliers import test 1'])->delete(true);
            Document::search(['name', '=', 'Suppliers import test 1'])->delete(true);
        }
    ],
    '0102' => [
        'description'       => "Tests that import data import works for suppliers_import.",
        'help'              => "
            Creates a document and a data import for suppliers with suppliers_import.xls file.
            Triggers action fmt_import_DataImport_import on data import.
            Assert that the suppliers were successful imported.
            Removes the created data import, document, identities and suppliers.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'suppliers_import.xlsx');

            $document = Document::create([
                'name' => 'Suppliers import test 2'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Suppliers import test 2',
                'document_id'   => $document['id'],
                'import_type'   => 'suppliers_import'
            ])
                ->update(['status' => 'ready'])
                ->read(['id'])
                ->first();
        },
        'act'               => function($data_import) {
            eQual::run('do', 'fmt_import_DataImport_import', ['id' => $data_import['id']]);
        },
        'assert'            => function() {
            $suppliers_ids = Supplier::search(['registration_number', 'in', ['0743926184', '0618459037', '0581742962', '0937604158', '0826091475']])->ids();
            $identities_ids = Identity::search(['registration_number', 'in', ['0743926184', '0618459037', '0581742962', '0937604158', '0826091475']])->ids();

            return count($suppliers_ids) === 5 && count($identities_ids) === 5;
        },
        'rollback'          => function() {
            DataImport::search(['name', '=', 'Suppliers import test 2'])->delete(true);
            Document::search(['name', '=', 'Suppliers import test 2'])->delete(true);

            Supplier::search(['registration_number', 'in', ['0743926184', '0618459037', '0581742962', '0937604158', '0826091475']])->delete(true);
            Identity::search(['registration_number', 'in', ['0743926184', '0618459037', '0581742962', '0937604158', '0826091475']])->delete(true);
        }
    ],
    '0201' => [
        'description'       => "Tests that check data import works for banks_import.",
        'help'              => "
            Creates a document and a data import for banks with banks_import.xls file.
            Triggers action fmt_import_DataImport_check on data import.
            Assert that the check was successful.
            Removes the created data import and document.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'banks_import.xlsx');

            $document = Document::create([
                'name' => 'Banks import test 1'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Banks import test 1',
                'document_id'   => $document['id'],
                'import_type'   => 'banks_import'
            ])
                ->read(['id'])
                ->first();
        },
        'act'               => function($data_import) {
            return eQual::run('do', 'fmt_import_DataImport_check', ['id' => $data_import['id']])['result'];
        },
        'assert'            => function($check_results) {
            return $check_results['errors'] === 0;
        },
        'rollback'          => function() {
            DataImport::search(['name', '=', 'Banks import test 1'])->delete(true);
            Document::search(['name', '=', 'Banks import test 1'])->delete(true);
        }
    ],
    '0202' => [
        'description'       => "Tests that import data import works for banks_import.",
        'help'              => "
            Creates a document and a data import for banks with banks_import.xls file.
            Triggers action fmt_import_DataImport_import on data import.
            Assert that the banks were successful imported.
            Removes the created data import, document, identities and banks.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'banks_import.xlsx');

            $document = Document::create([
                'name' => 'Banks import test 2'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Banks import test 2',
                'document_id'   => $document['id'],
                'import_type'   => 'banks_import'
            ])
                ->update(['status' => 'ready'])
                ->read(['id'])
                ->first();
        },
        'act'               => function($data_import) {
            eQual::run('do', 'fmt_import_DataImport_import', ['id' => $data_import['id']]);
        },
        'assert'            => function() {
            $banks_ids = Bank::search(['registration_number', 'in', ['0869211432', '0443859893', '0446220001', '0454506981', '0153596651']])->ids();
            $identities_ids = Identity::search(['registration_number', 'in', ['0869211432', '0443859893', '0446220001', '0454506981', '0153596651']])->ids();

            return count($banks_ids) === 5 && count($identities_ids) === 5;
        },
        'rollback'          => function() {
            DataImport::search(['name', '=', 'Banks import test 2'])->delete(true);
            Document::search(['name', '=', 'Banks import test 2'])->delete(true);

            Bank::search(['registration_number', 'in', ['0869211432', '0443859893', '0446220001', '0454506981', '0153596651']])->delete(true);
            Identity::search(['registration_number', 'in', ['0869211432', '0443859893', '0446220001', '0454506981', '0153596651']])->delete(true);
        }
    ],
    '0301' => [
        'description'       => "Tests that check data import works for condominium_import.",
        'help'              => "
            Creates a document and a data import for condominium with condominium_import.xls file.
            Triggers action fmt_import_DataImport_check on data import.
            Assert that the check was successful.
            Removes the created data import and document.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'condominium_import.xlsx');

            $document = Document::create([
                'name' => 'Condominium import test 1'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Condominium import test 1',
                'document_id'   => $document['id'],
                'import_type'   => 'condominium_import'
            ])
                ->read(['id'])
                ->first();
        },
        'act'               => function($data_import) {
            return eQual::run('do', 'fmt_import_DataImport_check', ['id' => $data_import['id']])['result'];
        },
        'assert'            => function($check_results) {
            return $check_results['errors'] === 0;
        },
        'rollback'          => function() {
            DataImport::search(['name', '=', 'Condominium import test 1'])->delete(true);
            Document::search(['name', '=', 'Condominium import test 1'])->delete(true);
        }
    ]
];
