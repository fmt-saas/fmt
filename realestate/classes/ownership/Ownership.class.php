<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\ownership;

use finance\accounting\Account;
use fmt\setting\Setting;

class Ownership extends \equal\orm\Model {


    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name representing the ownership (one or more persons).",
                'function'          => 'calcName',
                'readonly'          => true,
                'store'             => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                // 'required'          => true,
                'dependents'        => ['name', 'ownership_account_id']
            ],

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcOwnershipCode',
                'store'             => true,
                'description'       => "Code of the ownership.",
                'help'              => "Code is assigned automatically and cannot be changed, and is intended to internal use.",
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short optional description.",
                'store'             => true
            ],

            // #memo - this does not consider the date_from and date_to stored in propertyLotOwnership
            'property_lots_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'ownerships_ids',
                'rel_table'         => 'realestate_ownership_ownership_rel_property_lot',
                'rel_foreign_key'   => 'property_lot_id',
                'rel_local_key'     => 'ownership_id',
                'description'       => 'Property lots that are assigned to this ownership.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'property_lot_ownerships_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLotOwnership',
                'foreign_field'     => 'ownership_id',
                'description'       => 'Links of property lots currently assigned to this ownership.',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'owners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\ownership\Owner',
                'foreign_field'     => 'ownership_id',
                'description'       => 'List of owners.',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'dependents'        => ['name']
            ],

            'ownership_type' => [
                'type'              => 'string',
                'selection'         => [
                    'unique',
                    'joint'
                ],
                'description'       => "Type of ownership that applies to the owner.",
                'default'          => 'unique'
            ],

            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares of the ownership.",
                'default'           => 100,
                'visible'           => ['ownership_type' => 'joint'],
                'dependents'        => ['owners_ids' => 'ownership_percentage']
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date from which the owners owned at least one property lot.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Date at which the last owned lot was sold by the owners.",
                'help'              => "If set, targeted owners no longer own any lot in the condominium. But we keep the ownership for consistency and historical purposes.",
            ],

            'transfer_from_id' => [
                'type'              => 'many2one',
                'description'       => "The property purchase transfer file.",
                'foreign_object'    => 'realestate\property\OwnershipTransfer'
            ],

            'transfer_to_id' => [
                'type'              => 'many2one',
                'description'       => "The property sale transfer file.",
                'foreign_object'    => 'realestate\property\OwnershipTransfer'
            ],

            'creation_identity_id' => [
                'type'              => 'many2one',
                'description'       => "Identity of the owner.",
                'foreign_object'    => 'identity\Identity',
                'visible'           => ['state', '=', 'draft'],
                'help'              => 'This is a temporary field, which value is only used at creation to ease encoding and create a first owner.',
                'onupdate'          => 'onupdateCreationIdentityId'
            ],

            'representative_identity_id' => [
                'type'              => 'many2one',
                'description'       => "Person that represents the ownership.",
                'foreign_object'    => 'identity\Identity',
                'visible'           => ['has_representative', '=', true],
                'dependents'        => ['name']
            ],

            'has_representative' => [
                'type'              => 'boolean',
                'description'       => "Flag indicating if the ownership has a representative.",
                'default'           => false,
                'dependents'        => ['name']
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'ownership_id',
                'description'       => 'The fundings that relate to the ownership.'
            ],

            'ownership_account_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'function'          => 'calcOwnershipAccountId',
                'store'             => true
            ],

            'ownership_bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\OwnershipBankAccount',
                'foreign_field'     => 'ownership_id',
                'description'       => "The bank accounts of the ownership.",
                'domain'            => [['ownership_id', '=', 'object.id'], ['condo_id', '=', 'object.condo_id']]
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the Ownership.',
                'selection'         => [
                    'pending',
                    'validated'
                ],
                'default'           => 'pending'
            ]


        ];
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Ownership being completed, waiting to be validated.',
                'icon'        => 'edit',
                'transitions' => [
                    'validate' => [
                        'description' => 'Update the Ownership to `validated`.',
                        'policies'    => ['is_valid'],
                        'onafter'     => 'onafterValidate',
                        'status'      => 'validated'
                    ]
                ]
            ],
            'validated' => [
                'description' => 'Validated Ownership, ready to be used.',
                'icon'        => 'done
                ',
                'transitions' => [
                    'revert' => [
                        'description' => 'Revert to `pending` to allow changes.',
                        'policies'    => [/* #todo */],
                        'status'      => 'pending'
                    ]
                ]
            ]
        ];
    }

    public static function getActions() {
        return [
            'generate_accounts' => [
                'description'   => 'Generate mandatory accounting Accounts for Ownership.',
                'policies'      => [],
                'function'      => 'doGenerateAccounts'
            ],
            'generate_folders' => [
                'description'   => 'Generate folders for Ownership in Document repository.',
                'policies'      => [],
                'function'      => 'doGenerateFolders'
            ]
        ];
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Verifies that the mandatory values are present for Condominium validation.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    protected static function policyIsValid($self) {
        $result = [];

        $self->read(['condo_id', 'ownership_type', 'owners_ids', 'date_from', 'has_representative', 'representative_identity_id']);
        foreach($self as $id => $ownership) {

            if(!$ownership['condo_id']) {
                $result[$id] = [
                    'missing_condo_id' => 'The condominium must be provided.'
                ];
            }

            if($ownership['ownership_type'] === 'unique')  {
                if(count($ownership['owners_ids']) != 1)  {
                    $result[$id] = [
                        'invalid_owners_count' => 'For an ownership marked as unique, there should be exactly one owner.'
                    ];
                }
            }
            else {
                if(count($ownership['owners_ids']) < 2)  {
                    $result[$id] = [
                        'invalid_owners_count' => 'For an ownership marked as joint, there should be more than one owner.'
                    ];
                }
            }

            if(!$ownership['date_from']) {
                $result[$id] = [
                    'missing_date_from' => 'Date from is manadatory, if not known use the date of the Condominium creation.'
                ];
            }

            if($ownership['has_representative']) {
                if(!$ownership['representative_identity_id']) {
                    $result[$id] = [
                        'missing_representative_id' => 'The representative identity must be provided.'
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Retrieve the accounting account dedicated to owner (working fund)
     */
    protected static function calcOwnershipAccountId($self) {
        $result = [];
        $self->read(['condo_id', 'code']);
        foreach($self as $id => $ownership) {
            // find the account based on operation_assignment
            $account = Account::search([
                    ['condo_id', '=', $ownership['condo_id']],
                    ['operation_assignment', '=', 'co_owners_working_fund']
                ])
                ->read(['code'])
                ->first();

            if($account) {
                $ownerAccount = Account::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['code', '=', $account['code'] . $ownership['code']]
                    ])
                    ->first();
                $result[$id] = $ownerAccount['id'] ?? null;
            }
        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['code', 'has_representative', 'representative_identity_id' => ['name'], 'owners_ids' => ['name']]);
        foreach($self as $id => $ownership) {
            if(!$ownership['code']) {
                continue;
            }

            if($ownership['has_representative'] && isset($ownership['representative_identity_id']['name'])) {
                $result[$id] = $ownership['representative_identity_id']['name'];
            }
            else {
                $names = [];
                foreach($ownership['owners_ids'] as $owner_id => $owner) {
                    if($owner['name'] && strlen($owner['name'])) {
                        $names[] = $owner['name'];
                    }
                }
                $name = implode(', ', $names);
                if(strlen($name) > 128) {
                    $name = substr($name, 0, 128) . '...';
                }
                if(strlen($name) > 0) {
                    $result[$id] = $ownership['code'] . ' - ' . $name;
                }
                else {
                    $result[$id] = $ownership['code'];
                }
            }
        }
        return $result;
    }

    public static function calcOwnershipCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $ownership) {
            if($ownership['state'] != 'instance') {
                continue;
            }

            $sequence = Setting::fetch_and_add(
                    'realestate',
                    'organization',
                    'ownership.sequence',
                    1,
                    [
                        'condo_id' => $ownership['condo_id']
                    ]
                );

            if($sequence) {
                $result[$id] = sprintf("%05d", $sequence);
            }
        }
        return $result;
    }

    /**
     * Upon creation of an ownership, it is necessary to create accounts for:
     * - 4100xxxxx:        co_owners_reserve_fund
     * - 4101xxxxx:        co_owners_working_fund
     */
    public static function doGenerateAccounts($self) {
        $self->read(['condo_id', 'name', 'code']);
        foreach($self as $id => $ownership) {
            if(!$ownership['condo_id']) {
                continue;
            }
            $operation_assignments = [
                    'co_owners_reserve_fund',
                    'co_owners_working_fund'
                ];

            foreach($operation_assignments as $operation_assignment) {
                // find the account based on operation_assignment to use it as "template"
                $assignmentAccount = Account::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['operation_assignment', '=', $operation_assignment]
                    ])
                    ->read(['code', 'account_category', 'account_chart_id'])
                    ->first();

                if(!$assignmentAccount) {
                    throw new \Exception("missing_mandatory_account", EQ_ERROR_INVALID_CONFIG);
                }

                $account_exists = (bool) count(Account::search([['condo_id', '=', $ownership['condo_id']], ['code', '=', $assignmentAccount['code'] . $ownership['code']]])->ids());

                if(!$account_exists) {
                    Account::create([
                            'code'                  => $assignmentAccount['code'] . $ownership['code'],
                            'condo_id'              => $ownership['condo_id'],
                            'parent_account_id'     => $assignmentAccount['id'],
                            'account_chart_id'      => $assignmentAccount['account_chart_id'],
                            'account_category'      => $assignmentAccount['account_category'],
                            'description'           => $ownership['name'],
                            // make sure the account will not be used as template
                            'operation_assignment'  => ''
                        ])
                        ->read(['name']);
                }
            }

        }
    }

    public static function doGenerateFolders($self) {
        /*
        // #todo - unsure if necessary, not implemented for now
        $self->read(['condo_id']);
        foreach($self as $id => $ownership) {
            if(!$ownership['condo_id']) {
                continue;
            }
            // read 'default' journals (not assigned to any condominium)
            $folders = Node::search(['condo_id', '=', null])
                ->read([
                    'name',
                    'code',
                    'description'
                ]);

            // duplicate each folder/node
            foreach($folders as $folder_id => $folder) {
                Node::create([
                        'condo_id'      => $id,
                        "node_type"     => 'folder',
                        'name'          => $folder['name'],
                        'code'          => $folder['code'],
                        'description'   => $folder['description']
                    ]);
            }
        }
        */
    }

    public static function onupdateCreationIdentityId($self) {
        $self->read(['owners_ids', 'creation_identity_id', 'condo_id']);
        foreach($self as $id => $ownership) {
            if(count($ownership['owners_ids'])) {
                continue;
            }
            Owner::create([
                    'condo_id'      => $ownership['condo_id'],
                    'ownership_id'  => $id
                ])
                ->update([
                    'identity_id'   => $ownership['creation_identity_id']
                ]);
        }
    }

    protected static function onafterValidate($self) {
        $self
            ->do('generate_accounts')
            ->do('generate_folders');
    }

}
