<?php

use documents\Document;
use documents\navigation\Node;
use finance\accounting\Account;
use finance\accounting\AccountChart;
use finance\accounting\CurrentBalance;
use finance\accounting\FiscalPeriod;
use finance\accounting\FiscalYear;
use finance\accounting\Journal;
use finance\bank\Bank;
use finance\bank\BankAccount;
use finance\bank\SuppliershipBankAccount;
use fmt\import\DataImport;
use hr\role\RoleAssignment;
use identity\Identity;
use purchase\supplier\Supplier;
use purchase\supplier\Suppliership;
use realestate\finance\accounting\CondoFund;
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
                    'code',
                    'legal_name',
                    'managing_agent_id',
                    'cadastral_number',
                    'fiscal_year_start',
                    'fiscal_year_end',
                    'fiscal_period_frequency',
                    'expense_management_mode',
                    'role_assignments_ids',
                    'identity_id'               => ['type_id', 'type', 'legal_name', 'nationality', 'has_vat', 'vat_number', 'registration_number', 'lang_id', 'address_street', 'address_zip', 'address_city', 'address_country'],
                    'bank_accounts_ids'         => ['description', 'bank_account_iban', 'bank_account_type'],
                    'ownerships_ids'            => ['owners_ids', 'ownership_type'],
                    'property_entrances_ids'    => ['name', 'address_street', 'address_city', 'address_zip', 'address_country'],
                    'property_lots_ids'         => ['property_lot_ref'],
                    'apportionments_ids'        => ['description', 'total_shares']
                ])
                ->first(true);

            $expected_condominiums = [
                [
                    'code'                      => '000017',
                    'legal_name'                => 'ACP BRINDISI',
                    'managing_agent_id'         => 1,
                    'cadastral_number'          => '21392B0083/00E002',
                    'fiscal_year_start'         => strtotime('2022-07-01'),
                    'fiscal_year_end'           => strtotime('2023-06-30'),
                    'fiscal_period_frequency'   => 'A',
                    'expense_management_mode'   => 'provisions'
                ]
            ];
            if(!$checkItems($expected_condominiums, [$condominium])) {
                // condominium not created
                return false;
            }

            $expected_identities = [
                [
                    'type_id'               => 3,
                    'type'                  => 'CO',
                    'legal_name'            => 'ACP BRINDISI',
                    'nationality'           => 'BE',
                    'has_vat'               => false,
                    'vat_number'            => null,
                    'registration_number'   => '0848605092',
                    'lang_id'               => 2,
                    'address_street'        => 'Avenue Van Overbeke, 56',
                    'address_zip'           => 1083,
                    'address_city'          => 'Ganshoren',
                    'address_country'       => 'BE'
                ]
            ];
            if(!$checkItems($expected_identities, [$condominium['identity_id']])) {
                // condominium identity not created
                return false;
            }

            if(count($condominium['role_assignments_ids']) !== 2) {
                // condominium roles not assigned
                return false;
            }

            $expected_bank_accounts = [
                ['description' => 'Compte courant Belfius', 'bank_account_iban' => 'BE95068892422558', 'bank_account_type' => 'bank_current'],
                ['description' => 'Compte épargne Belfius', 'bank_account_iban' => 'BE97088251565249', 'bank_account_type' => 'bank_savings']
            ];
            if(!$checkItems($expected_bank_accounts, $condominium['bank_accounts_ids'])) {
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

            $expected_property_lots = [
                ['property_lot_ref' => 'A1'],
                ['property_lot_ref' => 'A2'],
                ['property_lot_ref' => 'B1'],
                ['property_lot_ref' => 'B2'],
                ['property_lot_ref' => 'C'],
                ['property_lot_ref' => 'GAR1-2'],
                ['property_lot_ref' => 'GAR3'],
                ['property_lot_ref' => 'GAR4'],
                ['property_lot_ref' => 'GAR5-6'],
                ['property_lot_ref' => 'Cave 1A'],
                ['property_lot_ref' => 'Cave 2A'],
                ['property_lot_ref' => 'Cave 1B'],
                ['property_lot_ref' => 'Cave 2B'],
                ['property_lot_ref' => 'Cave C']
            ];
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

            // 'Madame HUGE Johanna' not created because no ownership history
            $ownerships_address_recipient = ['Monsieur LINSKENS Cindy', 'Monsieur MEHMETI Sciprim', 'Madame VAN HAUWE Monique', 'Madame VAN HAUWE Yvette'];
            $com_pref_ownerships_ids = Ownership::search(['address_recipient', 'in', $ownerships_address_recipient])->ids();
            if(count($com_pref_ownerships_ids) !== count($ownerships_address_recipient)) {
                // communication preferences ownerships not as expected
                return false;
            }

            $communication_preferences = OwnershipCommunicationPreference::search([
                    ['condo_id', '=', $condominium['id']],
                    ['ownership_id', 'in', $com_pref_ownerships_ids]
                ])
                ->read(['id'])
                ->get(true);
            $preferences = ['general_assembly_call', 'general_assembly_minutes', 'expense_statement', 'fund_request', 'technical_communication'];
            $expected_communication_preferences = array_fill(0, count($preferences) * count($ownerships_address_recipient), []);
            if(!$checkItems($expected_communication_preferences, $communication_preferences)) {
                // lot communication preferences not as expected
                return false;
            }

            $expected_apportionments = [
                ['description' => 'Acte de base', 'total_shares' => 1000],
                ['description' => 'Charges communes', 'total_shares' => 1000]
            ];
            if(!$checkItems($expected_apportionments, $condominium['apportionments_ids'])) {
                // apportionments not as expected
                return false;
            }

            $apportionment_shares = PropertyLotApportionmentShare::search(['condo_id', '=', $condominium['id']])
                ->read(['property_lot_shares'])
                ->get(true);
            $expected_apportionment_shares = [
                ['property_lot_shares' => 163],
                ['property_lot_shares' => 163],
                ['property_lot_shares' => 124],
                ['property_lot_shares' => 124],
                ['property_lot_shares' => 263],
                ['property_lot_shares' => 64],
                ['property_lot_shares' => 16],
                ['property_lot_shares' => 19],
                ['property_lot_shares' => 64],
                ['property_lot_shares' => 163],
                ['property_lot_shares' => 163],
                ['property_lot_shares' => 124],
                ['property_lot_shares' => 124],
                ['property_lot_shares' => 263],
                ['property_lot_shares' => 64],
                ['property_lot_shares' => 16],
                ['property_lot_shares' => 19],
                ['property_lot_shares' => 64]
            ];
            if(!$checkItems($expected_apportionment_shares, $apportionment_shares)) {
                // apportionment shares not as expected
                return false;
            }

            $supplierships = Suppliership::search([
                ['condo_id', '=', $condominium['id']],
                ['supplier_id', '<>', 1]
            ])
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
                Suppliership::getType(),
                CondoFund::getType(),
                Node::getType(),
                Account::getType(),
                AccountChart::getType(),
                CurrentBalance::getType(),
                FiscalPeriod::getType(),
                FiscalYear::getType(),
                Journal::getType(),
                SuppliershipBankAccount::getType()
            ];
            foreach($types as $type) {
                $items_ids = $type::search(['condo_id', '=', $condominium['id']])->ids();
                $orm->delete($type, $items_ids, true);
            }
        }
    ],
];
