<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use finance\accounting\Account;
use fmt\setting\Setting;

class PropertyEntrance extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the entrance belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'readonly'          => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "Default apportionment to use for consumption accounts specific to this entrance.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'dependents'        => ['suffix']
            ],

            'duplication_accounts_ids' => [
                'type'              => 'many2many',
                'description'       => "Accounts to duplicate for this property entrance.",
                'help'              => "Only level-six control accounts without an operation assignment and ending in zero can be selected.",
                'foreign_object'    => 'finance\accounting\Account',
                'foreign_field'     => 'duplication_property_entrances_ids',
                'rel_table'         => 'realestate_propertyentrance_rel_account',
                'rel_local_key'     => 'property_entrance_id',
                'rel_foreign_key'   => 'account_id',
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['level', '=', 6],
                    ['is_control_account', '=', true],
                    ['operation_assignment', '=', ''],
                    ['code', 'like', '%0']
                ]
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the Entrance.",
                'multilang'         => true
            ],

            'suffix' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcSuffix',
                'description'       => "Suffix inherited from the selected apportionment when duplicating account hierarchies for this property entrance.",
                'store'             => true,
                'readonly'          => true,
                'multilang'         => true
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPropertyEntranceCode',
                'store'             => true,
                'description'       => "Code of the supplier for the Condominium.",
                'help'              => "Code is assigned automatically, cannot be changed, and is intended to internal use.",
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Optional description about the property entrance.'
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'help'              => "It is assumed that zip and city remain the same as the Condominium address. This field is deduced from parent Condominium but can be manually edited.",
            ],

            'address_city' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'address_city'],
                'store'             => true,
                'description'       => 'Street and number.',
                'help'              => "It is assumed that zip and city remain the same as the Condominium address. This field is deduced from parent Condominium but can be manually edited.",
            ],

            'address_zip' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'address_zip'],
                'store'             => true,
                'description'       => 'Postal code of the entrance (fall back to Condominium address).'
            ],

            'address_country' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['condo_id' => 'address_country'],
                'store'             => true,
                'description'       => 'Country of the entrance (fall back to Condominium address).'
            ],

            'property_lots_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'property_entrance_id',
                'description'       => "Property lots that use the entrance.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ]

        ];


    }

    public function getIndexes(): array {
        return [
            ['condo_id']
        ];
    }

    public static function getPolicies(): array {
        return array_merge(parent::getPolicies(), [
            'can_duplicate_accounts' => [
                'description' => 'Checks that an apportionment with a suffix is set before duplicating the selected account hierarchies.',
                'function'    => 'policyCanDuplicateAccounts'
            ]
        ]);
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'duplicate_accounts' => [
                'description' => 'Duplicates the selected account hierarchies for the property entrance.',
                'policies'    => ['can_duplicate_accounts'],
                'function'    => 'doDuplicateAccounts'
            ]
        ]);
    }

    protected static function policyCanDuplicateAccounts($self): array {
        $result = [];

        $self->read(['apportionment_id', 'suffix']);

        foreach($self as $id => $propertyEntrance) {
            if(!$propertyEntrance['apportionment_id']) {
                $result[$id] = [
                    'missing_apportionment_id' => 'An apportionment must be selected.'
                ];
            }
            if(!is_string($propertyEntrance['suffix']) || trim($propertyEntrance['suffix']) === '') {
                $result[$id]['missing_suffix'] = 'A non-empty suffix must be set on the selected apportionment.';
            }
        }

        return $result;
    }

    protected static function doDuplicateAccounts($self, $lang) {
        $self->read([
            'apportionment_id',
            'duplication_accounts_ids',
            'suffix'
        ], $lang);

        foreach($self as $id => $propertyEntrance) {
            foreach($propertyEntrance['duplication_accounts_ids'] as $account_id) {
                $accounts = Account::id($account_id);

                try {
                    $accounts->assertPolicies('can_duplicate_tree');
                }
                catch(\Exception $e) {
                    continue;
                }

                $sourceAccount = $accounts
                    ->read(['condo_id', 'account_chart_id', 'code'])
                    ->first();

                if(!$sourceAccount) {
                    continue;
                }

                $source_account_code = (string) $sourceAccount['code'];
                $index_length = substr($source_account_code, -2) === '00' ? 2 : 1;
                $code_prefix = substr($source_account_code, 0, -$index_length);
                $duplicateAccounts = Account::search([
                        ['condo_id', '=', $sourceAccount['condo_id']],
                        ['account_chart_id', '=', $sourceAccount['account_chart_id']],
                        ['apportionment_id', '=', $propertyEntrance['apportionment_id']],
                        ['level', '=', 6],
                        ['is_control_account', '=', true],
                        ['code', 'like', $code_prefix . '%']
                    ]);

                if($duplicateAccounts->count() > 0) {
                    continue;
                }

                $accounts->do('duplicate_tree', [
                    'apportionment_id' => $propertyEntrance['apportionment_id']
                ]);
            }
        }
    }

    protected static function calcSuffix($self, $lang) {
        $result = [];
        $self->read([
            'apportionment_id' => ['suffix']
        ], $lang);

        foreach($self as $id => $propertyEntrance) {
            if(!$propertyEntrance['apportionment_id']) {
                continue;
            }

            $result[$id] = $propertyEntrance['apportionment_id']['suffix'];
        }

        return $result;
    }

    protected static function calcPropertyEntranceCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $propertyEntrance) {
            if($propertyEntrance['state'] != 'instance') {
                continue;
            }

            $sequence = Setting::fetch_and_add(
                    'realestate',
                    'organization',
                    "property_entrance.sequence",
                    1,
                    [
                        'condo_id' => $propertyEntrance['condo_id']
                    ]
                );
            if($sequence) {
                $result[$id] = sprintf("%04d", $sequence);
            }
        }
        return $result;
    }


}
