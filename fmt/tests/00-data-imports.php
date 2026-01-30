<?php

use documents\Document;
use fmt\import\DataImport;

$tests = [
    '0101' => [
        'description'       => "Tests that check data import works for suppliers_import.",
        'help'              => "
            Creates a data import for suppliers with suppliers_import.xls document.
            Triggers action fmt_import_DataImport_check.
            Assert that the check was successful.
            Removes the created data import.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'suppliers_import.xlsx');

            $document = Document::create([
                'name' => 'Supplier import test 1'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Supplier import test 1',
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
            DataImport::search(['name', '=', 'Supplier import test 1'])->delete(true);
            Document::search(['name', '=', 'Supplier import test 1'])->delete(true);
        }
    ]
];
