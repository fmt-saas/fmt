<?php

use documents\Document;
use finance\bank\Bank;
use finance\bank\BankAccount;
use fmt\import\DataImport;
use hr\role\RoleAssignment;
use identity\Identity;
use purchase\supplier\Supplier;
use purchase\supplier\Suppliership;
use realestate\ownership\Owner;
use realestate\ownership\Ownership;
use realestate\ownership\OwnershipCommunicationPreference;
use realestate\property\Apportionment;
use realestate\property\Condominium;
use realestate\property\PropertyEntrance;
use realestate\property\PropertyLot;
use realestate\property\PropertyLotApportionmentShare;
use realestate\property\PropertyLotOwnership;

/**
 * @var \equal\orm\ObjectManager $orm
 */
['orm' => $orm] = eQual::inject(['orm']);

$checkItems = function($expected_items, $items) {
    if(count($items) !== count($expected_items)) {
        // condominium entrance not created
        return false;
    }
    foreach($expected_items as $index => $expected_item) {
        $item = $items[$index];
        foreach(array_keys($expected_item) as $field) {
            if($expected_item[$field] != $item[$field]) {
                // item value not expected
                return false;
            }
        }
    }

    return true;
};

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
    ],
    '0302' => [
        'description'       => "Tests that import data import works for condominium_import.",
        'help'              => "
            Creates a document and a data import for condominium with condominium_import.xls file.
            Triggers action fmt_import_DataImport_import on data import.
            Assert that the banks were successful imported.
            Removes the created data import, document, identities and condominium.
        ",
        'return'            => ['boolean'],
        'arrange'           => function() {
            $data = file_get_contents(EQ_BASEDIR . '/packages/fmt/tests/' . 'condominium_import.xlsx');

            $document = Document::create([
                'name' => 'Condominium import test 2'
            ])
                ->update(['data' => $data])
                ->read(['id'])
                ->first();

            return DataImport::create([
                'name'          => 'Condominium import test 2',
                'document_id'   => $document['id'],
                'import_type'   => 'condominium_import'
            ])
                ->update(['status' => 'ready'])
                ->read(['id'])
                ->first();
        },
        'act'               => function($data_import) {
            eQual::run('do', 'fmt_import_DataImport_import', ['id' => $data_import['id']]);
        },
        'assert'            => function() use($checkItems) {
            $condominium_code = str_pad('17', 6, '0', STR_PAD_LEFT);

            $condominium = Condominium::search(['code', '=', $condominium_code])
                ->read([
                    'identity_id',
                    'role_assignments_ids',
                    'bank_accounts_ids',
                    'ownerships_ids'            => ['owners_ids', 'ownership_type'],
                    'property_entrances_ids'    => ['name', 'address_street', 'address_city', 'address_zip', 'address_country'],
                    'property_lots_ids'         => [],
                    'apportionments_ids'        => []
                ])
                ->first(true);

            if(is_null($condominium)) {
                // condominium not created
                return false;
            }

            $condominium_identity = Identity::id($condominium['identity_id'])
                ->read(['id'])
                ->first();

            if(is_null($condominium_identity)) {
                // condominium identity not created
                return false;
            }

            if(count($condominium['role_assignments_ids']) !== 2) {
                // condominium roles not assigned
                return false;
            }

            if(count($condominium['bank_accounts_ids']) !== 2) {
                // condominium roles not assigned
                return false;
            }

            $owners_identities_ids = Identity::search(['email', 'in', ['johanna.huge@outlook.com', 'cindylinskens@hotmail.com', 'shqiprim-mehmeti96@outlook.com', 'van_hauwe_monique@hotmail.com', 'yvette_van_hauwe@yahoo.com']])->ids();
            if(count($owners_identities_ids) !== 5) {
                // owners identities not created
                return false;
            }

            if(count($condominium['ownerships_ids']) !== 4) {
                // condominium ownerships not assigned
                return false;
            }

            foreach($condominium['ownerships_ids'] as $ownership) {
                if(count($ownership['owners_ids']) !== 1) {
                    // condominium ownerships owner not assigned
                    return false;
                }
            }

            foreach($condominium['ownerships_ids'] as $ownership) {
                if($ownership['ownership_type'] !== 'unique') {
                    // condominium ownerships ownership_type not equal to joint
                    return false;
                }
            }

            $expected_entrances = [
                [
                    'name'              => 'ACP BRINDISI',
                    'address_street'    => 'Avenue Van Overbeke, 56',
                    'address_city'      => 'Ganshoren',
                    'address_zip'       => '1083',
                    'address_country'   => 'BE'
                ]
            ];
            if(!$checkItems($expected_entrances, $condominium['property_entrances_ids'])) {
                // entrance not as expected
                return false;
            }

            $expected_property_lots = array_fill(0, 14, []);
            if(!$checkItems($expected_property_lots, $condominium['property_lots_ids'])) {
                // lots not as expected
                return false;
            }

            $property_lot_ownerships = PropertyLotOwnership::search(['condo_id', '=', $condominium['id']])
                ->read(['id'])
                ->get(true);
            $expected_property_lot_ownerships = array_fill(0, 14, []);
            if(!$checkItems($expected_property_lot_ownerships, $property_lot_ownerships)) {
                // lot ownerships not as expected
                return false;
            }

            $communication_preferences = OwnershipCommunicationPreference::search(['condo_id', '=', $condominium['id']])
                ->read(['id'])
                ->get(true);
            $expected_communication_preferences = array_fill(0, 4, []);
            if(!$checkItems($expected_communication_preferences, $communication_preferences)) {
                // lot communication preferences not as expected
                return false;
            }

            $expected_apportionments = array_fill(0, 2, []);
            if(!$checkItems($expected_apportionments, $condominium['apportionments_ids'])) {
                // apportionments not as expected
                return false;
            }

            $apportionment_shares = PropertyLotApportionmentShare::search(['condo_id', '=', $condominium['id']])
                ->read(['id'])
                ->get(true);
            $expected_apportionment_shares = array_fill(0, 18, []);
            if(!$checkItems($expected_apportionment_shares, $apportionment_shares)) {
                // apportionment shares not as expected
                return false;
            }

            $supplierships = Suppliership::search(['condo_id', '=', $condominium['id']])
                ->read(['id'])
                ->get(true);
            $expected_supplierships = array_fill(0, 1, []);
            if(!$checkItems($expected_supplierships, $supplierships)) {
                // supplierships not as expected
                return false;
            }

            return true;
        },
        'rollback'          => function() use($orm) {
            DataImport::search(['name', '=', 'Condominium import test 2'])->delete(true);
            Document::search(['name', '=', 'Condominium import test 2'])->delete(true);

            $condominium_code = str_pad('17', 6, '0', STR_PAD_LEFT);
            $condominium = Condominium::search(['code', '=', $condominium_code])
                ->read(['identity_id'])
                ->first();

            // condominium and its identity
            $orm->delete(Condominium::getType(), $condominium['id'], true);
            $orm->delete(Identity::getType(), $condominium['identity_id'], true);

            // owners identities
            $identities_ids = Identity::search(['email', 'in', ['johanna.huge@outlook.com', 'cindylinskens@hotmail.com', 'shqiprim-mehmeti96@outlook.com', 'van_hauwe_monique@hotmail.com', 'yvette_van_hauwe@yahoo.com']])->ids();
            $orm->delete(Identity::getType(), $identities_ids, true);

            // other related items
            $types = [
                RoleAssignment::getType(),
                BankAccount::getType(),
                Ownership::getType(),
                Owner::getType(),
                PropertyEntrance::getType(),
                PropertyLot::getType(),
                PropertyLotOwnership::getType(),
                OwnershipCommunicationPreference::getType(),
                Apportionment::getType(),
                PropertyLotApportionmentShare::getType(),
                Suppliership::getType()
            ];
            foreach($types as $type) {
                $items_ids = $type::search(['condo_id', '=', $condominium['id']])->ids();
                $orm->delete($type, $items_ids, true);
            }
        }
    ],
];
