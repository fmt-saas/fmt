<?php

use documents\Document;
use fmt\import\DataImport;

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
        'description'       => "Tests that check data import works for banks_import.",
        'help'              => "
            Creates a document and a data import for suppliers with banks_import.xls file.
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
    ]
];
