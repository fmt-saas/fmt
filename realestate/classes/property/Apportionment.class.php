<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use core\Lang;
use finance\accounting\Account;
use fmt\setting\Setting;

class Apportionment extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['code']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => "Name of the apportionment.",
                'store'             => true,
                'readonly'          => true,
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the apportionment.",
                'required'          => true,
                'multilang'         => true,
                'dependents'        => ['name']
            ],

            'suffix' => [
                'type'              => 'string',
                'description'       => "Suffix appended to account names during duplication.",
                'multilang'         => true,
                'onupdate'          => 'onupdateSuffix'
            ],

            'duplication_accounts_ids' => [
                'type'              => 'many2many',
                'description'       => "Accounts to duplicate for this apportionment.",
                'help'              => "Only level-six control accounts without an operation assignment and ending in zero can be selected.",
                'foreign_object'    => 'finance\accounting\Account',
                'foreign_field'     => 'duplication_apportionments_ids',
                'rel_table'         => 'realestate_apportionment_rel_account',
                'rel_local_key'     => 'apportionment_id',
                'rel_foreign_key'   => 'account_id',
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id'],
                    ['level', '=', 6],
                    ['is_control_account', '=', true],
                    ['operation_assignment', '=', ''],
                    ['code', 'like', '%0']
                ]
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcApportionmentCode',
                'store'             => true,
                'description'       => "Code for the apportionment.",
                'help'              => "Code is arbitrary and is used to match apportionment with accounting accounts.",
                'dependents'        => ['name']
            ],

            'is_statutory' => [
                'type'              => 'boolean',
                'description'       => "The apportionment holds the statutory quotas.",
                'help'              => "Apportionment describes the rights on the condominium's common areas as defined in the notary deed.",
                'default'           => false,
                'dependents'        => ['name', 'code']
            ],

            'common_area_id' => [
                'type'              => 'many2one',
                'description'       => "The type of the common area.",
                'foreign_object'    => 'realestate\property\CommonAreaType',
                'visible'           => ['is_statutory', '=', false]
            ],

            'apportionment_shares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotApportionmentShare',
                'foreign_field'     => 'apportionment_id',
                'ondetach'          => 'delete',
                'description'       => "The shares referring to the apportionment.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['apportionment_id', '=', 'object.id']]
            ],

            /*
            // #memo - this creates a unique constraint on [property_lot_id, apportionment_id], while condo_id is also part of the key
            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'apportionments_ids',
                'rel_table'         => 'realestate_property_propertylotapportionmentshare',
                'rel_foreign_key'   => 'property_lot_id',
                'rel_local_key'     => 'apportionment_id',
                'description'       => 'Property lots that are assigned to this key.'
            ],
            */

            'count_property_lots' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCountPropertyLots',
                'description'       => 'Total amount of property lots assigned to this nature.',
                'store'             => true
            ],

            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares considered for this apportionment.",
                'default'           => 1000,
                'dependents'        => ['name']
            ],

            'assigned_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcAssignedShares',
                'description'       => "The total number of assigned shares (for control).",
                'store'             => false
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Flag marking the apportionment as active.",
                'help'              => "Apportionments cannot be removed, but marking them as non-active hide them in the selection lists.",
                'default'           => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the Apportionment.'
            ]

        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Draft apportionment, still waiting to be completed for validation.',
                'icon' => 'draw',
                'transitions' => [
                    'validate' => [
                        'description' => 'Publish the Apportionment (this cannot be undone).',
                        'policies'    => ['can_validate'],
                        'onbefore'    => 'onbeforeValidate',
                        'status'      => 'validated',
                    ]
                ]
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'can_validate' => [
                'description' => 'Verifies that the Apportionment can be validated.',
                'function'    => 'policyCanValidate'
            ],
            'can_duplicate_accounts' => [
                'description' => 'Checks that a suffix is set in every language before duplicating account hierarchies.',
                'function'    => 'policyCanDuplicateAccounts'
            ]
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'duplicate' => [
                'description'   => 'Creates a new invoice of type credit note to reverse invoice.',
                'help'          => 'Reversing an invoice can only be done when status is "invoice".',
                'policies'      => [],
                'function'      => 'doDuplicateApportionment'
            ],
            'duplicate_accounts' => [
                'description' => 'Duplicates the selected account hierarchies using the current apportionment.',
                'policies'    => ['can_duplicate_accounts'],
                'function'    => 'doDuplicateAccounts'
            ],
            'reset_account_suffixes' => [
                'description' => 'Resets the suffix of all accounts linked to the apportionment in every language.',
                'policies'    => [],
                'function'    => 'doResetAccountSuffixes'
            ]
        ]);
    }

    protected static function onupdateSuffix($self) {
        $self->do('reset_account_suffixes');
    }

    protected static function doResetAccountSuffixes($self) {
        $accounts = Account::search([
            ['apportionment_id', 'in', $self->ids()]
        ]);

        if($accounts->count() === 0) {
            return;
        }

        $languages = Lang::search()->read(['code']);

        foreach($languages as $language) {
            $accounts->update(['suffix' => null], $language['code']);
        }
    }

    protected static function policyCanDuplicateAccounts($self): array {
        $result = [];
        $languages = Lang::search()->read(['code']);

        foreach($languages as $language) {
            $self->read(['suffix'], $language['code']);

            foreach($self as $id => $apportionment) {
                if(!is_string($apportionment['suffix']) || trim($apportionment['suffix']) === '') {
                    $result[$id] = [
                        'missing_suffix' => 'A non-empty suffix must be set in every language.'
                    ];
                }
            }
        }

        return $result;
    }

    protected static function policyCanValidate($self) {
        $result = [];

        $self->read(['status', 'assigned_shares', 'total_shares']);

        foreach($self as $id => $apportionment) {
            if($apportionment['status'] === 'validated') {
                $result[$id] = [
                    'not_allowed' => 'Apportionment is already validated and active.'
                ];
            }
            if($apportionment['total_shares'] <= 0) {
                $result[$id] = [
                    'invalid_apportionment' => 'Apportionment must have a non-null amount of shares.'
                ];
            }
            if(round($apportionment['assigned_shares'], 5) != round($apportionment['total_shares'], 5)) {
                trigger_error('APP::mismatch total_shares and assigned_shares for apportionment ' . $id . ' : ' . round($apportionment['assigned_shares'], 5) . ' ' . round($apportionment['total_shares'], 5) , EQ_REPORT_ERROR);
                $result[$id] = [
                    'not_balanced' => 'Assigned shares does not match apportionment total.'
                ];
            }
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['status']);
        $allowed_fields = ['code', 'description', 'suffix', 'duplication_accounts_ids', 'condo_id'];
        foreach($self as $id => $apportionment) {
            // #todo - prevent using the same code twice for a same condominium
            if($apportionment['status'] == 'validated') {
                if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                    return ['status' => ['not_allowed' => 'Validated apportionment cannot be modified.']];
                }
            }
        }
        return parent::canupdate($self);
    }

    public static function onbeforeValidate($self) {
        $self->update([
            // #memo - updated at creation & duplication
            // 'code' => null,
            'name' => null
        ]);
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['state', 'status', 'is_statutory', 'total_shares', 'code', 'description']);
        foreach($self as $id => $apportionment) {
            if($apportionment['state'] != 'instance') {
                continue;
            }
            if(!$apportionment['code']) {
                continue;
            }
            $name = ($apportionment['is_statutory']) ? '' : $apportionment['code'] . ' - ';
            $result[$id] = $name . $apportionment['description'] .' (Q. '.$apportionment['total_shares'].')';
        }
        return $result;
    }

    public static function calcApportionmentCode($self) {
        $result = [];
        $self->read(['state', 'status', 'is_statutory', 'condo_id']);
        foreach($self as $id => $apportionment) {
            if($apportionment['state'] != 'instance') {
                continue;
            }
            if($apportionment['is_statutory']) {
                $result[$id] = 'STAT';
            }
            else {
                $sequence = Setting::fetch_and_add(
                        'realestate',
                        'organization',
                        "apportionment.sequence",
                        1,
                        [
                            'condo_id' => $apportionment['condo_id']
                        ]
                    );

                if($sequence) {
                    $result[$id] = sprintf("%04d", $sequence);
                }
            }
        }
        return $result;
    }

    public static function calcAssignedShares($self) {
        $result = [];
        $self->read(['apportionment_shares_ids' => ['property_lot_shares']]);
        foreach($self as $id => $apportionment) {
            $result[$id] = 0;
            foreach($apportionment['apportionment_shares_ids'] as $share) {
                $result[$id] += $share['property_lot_shares'];
            }
        }
        return $result;

    }

    public static function calcCountPropertyLots($self) {
        $result = [];
        $self->read(['apportionment_shares_ids']);
        foreach($self as $id => $key) {
            $result[$id] = count($key['apportionment_shares_ids'] ?? []);
        }
        return $result;
    }

    public static function doDuplicateApportionment($self) {
        $self->read(['condo_id', 'description', 'is_statutory', 'common_area_id', 'total_shares', 'apportionment_shares_ids']);

        foreach($self as $id => $apportionment) {
            // 1) create a new Apportionment with (copy) as prefix
            $new_apportionment = self::create([
                    'condo_id'         => $apportionment['condo_id'],
                    'description'      => '(copy) ' . $apportionment['description'],
                    'is_statutory'     => $apportionment['is_statutory'],
                    'common_area_id'   => $apportionment['common_area_id'],
                    'total_shares'     => $apportionment['total_shares']
                ])
                ->first();

            // 2) copy lines
            $shares = PropertyLotApportionmentShare::ids($apportionment['apportionment_shares_ids'])->read(['property_lot_id', 'property_lot_shares']);
            foreach($shares as $share) {
                PropertyLotApportionmentShare::create([
                        'condo_id'              => $apportionment['condo_id'],
                        'apportionment_id'      => $new_apportionment['id'],
                        'property_lot_id'       => $share['property_lot_id'],
                        'property_lot_shares'   => $share['property_lot_shares'],
                        'is_statutory'          => false
                    ]);
            }
        }

    }

    protected static function doDuplicateAccounts($self, $lang) {
        $self->read([
            'duplication_accounts_ids',
            'suffix'
        ], $lang);

        foreach($self as $id => $apportionment) {
            foreach($apportionment['duplication_accounts_ids'] as $account_id) {
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
                        ['apportionment_id', '=', $id],
                        ['level', '=', 6],
                        ['is_control_account', '=', true],
                        ['code', 'like', $code_prefix . '%']
                    ]);

                if($duplicateAccounts->count() > 0) {
                    continue;
                }

                $accounts->do('duplicate_tree', [
                    'apportionment_id' => $id
                ]);
            }
        }
    }
}
