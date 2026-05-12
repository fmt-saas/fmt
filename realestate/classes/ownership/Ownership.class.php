<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\ownership;

use finance\accounting\Account;
use fmt\setting\Setting;
use realestate\property\PropertyLotOwnership;

class Ownership extends \equal\orm\Model {

    public static function getLink() {
        return "/app/#/condo/:condo_id/ownership/object.id";
    }

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
                'dependents'        => ['name', 'code', 'payment_reference']
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

            'extref_owner_reference' => [
                'type'              => 'string',
                'description'       => "Arbitrary reference to the Ownership, as used in an external software (for imports).",
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Short optional description.",
                'store'             => true
            ],

            // #memo - this does not consider the date_from and date_to stored in propertyLotOwnership
            /*
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
            */

            'property_lots_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'active_ownership_id',
                'description'       => 'Property lots that are currently assigned to this ownership.',
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

            'ownership_shares' => [
                'type'              => 'float',
                'usage'             => 'number/real:8.6',
                'description'       => "The total number of shares of the ownership.",
                'help'              => "This value is meant to allow splitting the title between several owners (e.g. in case of joint ownership)",
                'default'           => 100,
                'visible'           => ['ownership_type', '=', 'joint'],
                'dependents'        => ['owners_ids' => 'ownership_percentage']
            ],

            'statutory_shares' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Sum of Statutory shares of the property lot of the Ownership.",
                'function'          => 'calcStatutoryShares',
                'store'             => false,
                'readonly'          => true
            ],

            // #memo - an Ownership might be linked to several Accounts of the Accounting Chart
            // 'accounting_account_id'
            // 'accounting_accounts_ids'

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date from which the owners owned at least one property lot.",
                'help'              => 'If this value is unknown, set it to first day of first fiscal year',
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

            'address_recipient' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'function'          => 'calcAddressRecipient',
                // #memo - on n'a pas le lien de parenté
                'description'       => "Line to be used for sending courier to the Ownership representative(s)."
            ],

            'has_external_representative' => [
                'type'              => 'boolean',
                'description'       => "Flag indicating if the ownership has a (internal) representative.",
                'default'           => false,
                'dependents'        => ['name']
            ],

            'representative_identity_id' => [
                'type'              => 'many2one',
                'description'       => "External person that represents the ownership.",
                'help'              => "External person that has a mandate for representing the ownership, but is not amongst the owners.",
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['type_id', '=', 1],
                'visible'           => ['has_external_representative', '=', true],
                'dependents'        => ['name']
            ],

            'has_representative' => [
                'type'              => 'boolean',
                'description'       => "Flag indicating if the ownership has a (internal) representative.",
                'help'              => "Law states that there should be one representative for a joint-ownership, but if owners cannot agree, Syndic has to send documents to all owners.",
                'default'           => false,
                'dependents'        => ['name']
            ],

            'representative_owner_id' => [
                'type'              => 'many2one',
                'description'       => "Main owner that represents the ownership.",
                'help'              => "Owner (amongst the owners) designated by the joint ownership for representing the ownership.
                    As of BE Law on Co-ownership - Article 3.87, § 1.",
                'foreign_object'    => 'realestate\ownership\Owner',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['ownership_id', '=', 'object.id']],
                'dependents'        => ['address_recipient']
            ],

            // représentants secondaires (pour générer d'autres lignes de communication) - uniquement
            //  #todo - pas sûr : cela est peut être mieux géré dans les préférences de communication
            // 'representative_owners_ids' => [

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Funding',
                'foreign_field'     => 'ownership_id',
                'description'       => 'The fundings that relate to the ownership.'
            ],

            'ownership_bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\OwnershipBankAccount',
                'foreign_field'     => 'ownership_id',
                'description'       => "The bank accounts of the ownership.",
                'domain'            => [['ownership_id', '=', 'object.id'], ['condo_id', '=', 'object.condo_id']]
            ],

            'ownership_communication_preferences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\ownership\OwnershipCommunicationPreference',
                'foreign_field'     => 'ownership_id',
                'description'       => "The communication preferences of the ownership.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'assemblies_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\governance\Assembly',
                'foreign_field'     => 'ownerships_ids',
                'rel_table'         => 'realestate_governance_assembly_rel_ownership',
                'rel_foreign_key'   => 'assembly_id',
                'rel_local_key'     => 'ownership_id',
                'description'       => "Assemblies by which the ownership have been concerned over time."
            ],

            'attendees_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\governance\AssemblyAttendee',
                'foreign_field'     => 'ownerships_ids',
                'rel_table'         => 'realestate_governance_attendee_rel_ownership',
                'rel_foreign_key'   => 'attendee_id',
                'rel_local_key'     => 'ownership_id',
                'description'       => "Attendees that have represented the ownership represented over time."
            ],

            'setting_values_ids' => [
                'type'              => 'one2many',
                'description'       => "The apportionment keys relating to the ownership.",
                'foreign_object'    => 'fmt\setting\SettingValue',
                'foreign_field'     => 'ownership_id',
                'domain'            => ['ownership_id', '=', 'object.id']
            ],

            'setting_sequences_ids' => [
                'type'              => 'one2many',
                'description'       => "The apportionment keys relating to the ownership.",
                'foreign_object'    => 'fmt\setting\SettingSequence',
                'foreign_field'     => 'ownership_id',
                'domain'            => ['ownership_id', '=', 'object.id']
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Message for identifying the ownership in bank statements.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true,
                'function'          => 'calcPaymentReference'
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

    public function getIndexes(): array {
        return [
            ['condo_id']
        ];
    }

    protected static function calcAddressRecipient($self) {
        $result = [];

        $self->read(['owners_ids' => ['firstname', 'lastname', 'gender', 'lang_id']]);

        foreach($self as $id => $ownership) {
            $owners = $ownership['owners_ids'];

            if(empty($owners)) {
                $result[$id] = '';
                continue;
            }

            // Langue du premier owner (1=EN, 2=FR, 3=NL)
            $firstOwner = $owners->first();
            $lang_id = isset($firstOwner['lang_id']) ? (int) $firstOwner['lang_id'] : 2;

            // map of salutation titles, based on lang
            $titles = [];
            if($lang_id === 1) {
                $titles = ['M' => 'Mr', 'F' => 'Mrs', 'X' => 'Mx', '' => ''];
                $and = 'and';
            }
            elseif($lang_id === 3) {
                $titles = ['M' => 'De heer', 'F' => 'Mevrouw', 'X' => '', '' => ''];
                $and = 'en';
            }
            else {
                $titles = ['M' => 'Monsieur', 'F' => 'Madame', 'X' => '', '' => ''];
                $and = 'et';
            }

            // group owners by gender
            $groups = ['M' => [], 'F' => [], 'X' => [], '' => []];

            foreach($owners as $owner) {
                $gender = strtoupper(trim($owner['gender'] ?? ''));
                if(!in_array($gender, ['M', 'F', 'X'])) {
                    $gender = '';
                }
                $lastname = trim($owner['lastname'] ?? '');
                $firstname = trim($owner['firstname'] ?? '');
                if($lastname !== '') {
                    $groups[$gender][] = [
                        'firstname' => $firstname,
                        'lastname'  => $lastname
                    ];
                }
            }

            $count = count($owners);

            // Case 1 : single owner
            if($count === 1) {
                $owner = $firstOwner;
                $gender = strtoupper(trim($owner['gender'] ?? ''));
                $lastname = trim($owner['lastname'] ?? '');
                $title = isset($titles[$gender]) ? $titles[$gender] : '';
                $result[$id] = trim($title . ' ' . $lastname);
                continue;
            }

            // Case 2 : husband and wife with same name
            if(!empty($groups['M']) && !empty($groups['F']) && empty($groups['X']) && empty($groups[''])) {
                $lnM = array_column($groups['M'], 'lastname');
                $lnF = array_column($groups['F'], 'lastname');
                $merged = array_unique(array_merge($lnM, $lnF));
                if(count($merged) === 1) {
                    $lastname = $merged[0];
                    $result[$id] = $titles['M'] . ' ' . $and . ' ' . $titles['F'] . ' ' . $lastname;
                    continue;
                }
                $maleNames = [];
                foreach($groups['M'] as $o) {
                    $maleNames[] = trim($titles['M'] . ' ' . $o['lastname']);
                }
                $femaleNames = [];
                foreach($groups['F'] as $o) {
                    $femaleNames[] = trim($titles['F'] . ' ' . $o['lastname']);
                }
                $result[$id] = implode(' ' . $and . ' ', array_merge($maleNames, $femaleNames));
                continue;
            }

            // Case 3 : a single common gender
            $filtered = array_filter($groups);
            if(count($filtered) === 1) {
                $key = array_key_first($filtered);
                $ownersList = $groups[$key];
                $title = isset($titles[$key]) ? $titles[$key] : '';
                $lastnames = array_unique(array_column($ownersList, 'lastname'));
                if(count($lastnames) === 1) {
                    switch($lang_id) {
                        case 1: // EN
                            if($key === 'M') {
                                $title = 'Messrs';
                            }
                            elseif($key === 'F') {
                                $title = 'Madams';
                            }
                            break;
                        case 2: // FR
                            if($key === 'M') {
                                $title = 'Messieurs';
                            }
                            elseif($key === 'F') {
                                $title = 'Mesdames';
                            }
                            break;
                        case 3: // NL
                            if($key === 'M') {
                                $title = 'De heren';
                            }
                            elseif ($key === 'F') {
                                $title = 'Dames';
                            }
                            break;
                    }
                    $result[$id] = trim($title . ' ' . $lastnames[0]);
                    continue;
                }
                else {
                    $formatted = [];
                    foreach($ownersList as $o) {
                        $formatted[] = trim($title . ' ' . $o['lastname']);
                    }
                    $result[$id] = implode(' ' . $and . ' ', $formatted);
                    continue;
                }
            }

            // Case 4 : mixed up or unknown
            $names = [];
            foreach($owners as $owner) {
                $gender = strtoupper(trim($owner['gender'] ?? ''));
                $lastname = trim($owner['lastname'] ?? '');
                $title = isset($titles[$gender]) ? $titles[$gender] : '';
                $names[] = trim($title . ' ' . $lastname);
            }

            $result[$id] = implode(' ' . $and . ' ', array_unique(array_filter($names)));
        }

        return $result;
    }

    protected static function calcStatutoryShares($self) {
        $result = [];
        $self->read(['state', 'property_lots_ids' => ['statutory_shares']]);
        foreach($self as $id => $ownership) {
            if($ownership['state'] === 'draft') {
                continue;
            }
            $result[$id] = 0.0;
            foreach($ownership['property_lots_ids'] as $property_lot_id => $propertyLot) {
                $result[$id] += $propertyLot['statutory_shares'] ?? 0;
            }
        }
        return $result;
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
                'icon'        => 'done',
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
            'normalize_representative_owner' => [
                'description'   => 'Generate mandatory accounting Accounts for Ownership.',
                'policies'      => [],
                'function'      => 'doNormalizeRepresentativeOwner'
            ],
            'generate_accounts' => [
                'description'   => 'Generate mandatory accounting Accounts for Ownership.',
                'policies'      => [],
                'function'      => 'doGenerateAccounts'
            ],
            'generate_folders' => [
                'description'   => 'Generate folders for Ownership in Document repository.',
                'policies'      => [],
                'function'      => 'doGenerateFolders'
            ],
            'generate_communication_prefs' => [
                'description'   => 'Generate Communication Preferences for Ownership.',
                'policies'      => [],
                'function'      => 'doGenerateCommunicationPrefs'
            ],
            'generate_history' => [
                'description'   => 'Used at validation to ensure a PropertyLotOwnership exists.',
                'policies'      => [],
                'function'      => 'doGenerateHistory'
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

        $self->read(['condo_id', 'ownership_type', 'owners_ids', 'date_from', 'has_external_representative', 'representative_identity_id']);
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
                    'missing_date_from' => 'Date from is mandatory, if not known use the date of the Condominium creation.'
                ];
            }

            if($ownership['has_external_representative']) {
                if(!$ownership['representative_identity_id']) {
                    $result[$id] = [
                        'missing_representative_id' => 'The representative identity must be provided.'
                    ];
                }
            }
/*
#todo - vérif cohérence parts de propriétaires (owners)


*/

        }
        return $result;
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['code', 'owners_ids' => ['name']]);
        foreach($self as $id => $ownership) {
            if(!$ownership['code']) {
                continue;
            }

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
        return $result;
    }

    public static function calcOwnershipCode($self) {
        $result = [];
        $self->read(['state', 'condo_id']);
        foreach($self as $id => $ownership) {
            if($ownership['state'] != 'instance') {
                continue;
            }

            if(!$ownership['condo_id']) {
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
     * 410 co_owners
     * 4100 reserve funds
     * 4101 working funds
     *
     * Upon creation of an ownership, it is necessary to create accounts for:
     * - 410xxxxx:         [Ownership collector] -> co_owners_reserve_fund + co_owners_working_fund
     * - 4100xxxxx:        co_owners_reserve_fund
     * - 4101xxxxx:        co_owners_working_fund
     *
     */
    public static function doGenerateAccounts($self) {
        $self->read(['condo_id', 'name', 'code']);

        foreach($self as $id => $ownership) {
            if(!$ownership['condo_id']) {
                continue;
            }

            $assignmentAccount = Account::search([
                    ['condo_id', '=', $ownership['condo_id']],
                    ['operation_assignment', '=', 'co_owners'],
                    ['ownership_id', '=', null]
                ])
                ->read(['code', 'account_chart_id', 'account_category'])
                ->first();

            if(!$assignmentAccount) {
                trigger_error("APP::Could not find account candidate for condominium {$ownership['condo_id']} for operation assignment `co_owners`", EQ_REPORT_ERROR);
                throw new \Exception("missing_mandatory_account", EQ_ERROR_INVALID_CONFIG);
            }

            $parentAccount = Account::search([
                    ['condo_id', '=', $ownership['condo_id']],
                    ['code', '=', $assignmentAccount['code'] . $ownership['code']]
                ])
                ->read(['id', 'code'])
                ->first();

            if(!$parentAccount) {
                $parentAccount = Account::create([
                        'code'                  => $assignmentAccount['code'] . $ownership['code'],
                        'condo_id'              => $ownership['condo_id'],
                        'account_chart_id'      => $assignmentAccount['account_chart_id'],
                        'account_category'      => $assignmentAccount['account_category'],
                        'description'           => $ownership['name'],
                        'is_control_account'    => true,
                        'operation_assignment'  => 'co_owners_owner',
                        'ownership_id'          => $id,
                        'parent_account_id'     => $assignmentAccount['id']
                    ])
                    ->read(['id', 'code'])
                    ->first();
            }

            $operation_assignments = [
                    'co_owners_reserve_fund',
                    'co_owners_working_fund'
                ];

            foreach($operation_assignments as $operation_assignment) {
                // find the account based on operation_assignment to use it as "template"
                $assignmentAccount = Account::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['operation_assignment', '=', $operation_assignment],
                        ['ownership_id', '=', null]
                    ])
                    ->read(['code', 'account_category', 'account_chart_id'])
                    ->first();

                if(!$assignmentAccount) {
                    trigger_error("APP::Could not find account candidate for condominium {$ownership['condo_id']} for operation assignment $operation_assignment", EQ_REPORT_ERROR);
                    throw new \Exception("missing_mandatory_account", EQ_ERROR_INVALID_CONFIG);
                }

                $account_exists = (bool) count(Account::search([['condo_id', '=', $ownership['condo_id']], ['code', '=', $assignmentAccount['code'] . $ownership['code']]])->ids());

                if(!$account_exists) {
                    Account::create([
                            'code'                  => $assignmentAccount['code'] . $ownership['code'],
                            'condo_id'              => $ownership['condo_id'],
                            'account_chart_id'      => $assignmentAccount['account_chart_id'],
                            'account_category'      => $assignmentAccount['account_category'],
                            'description'           => $ownership['name'],
                            'operation_assignment'  => $operation_assignment,
                            'ownership_id'          => $id,
                            'parent_account_id'     => $parentAccount['id']
                        ])
                        ->read(['name']);
                }
            }

        }
    }

    /**
     * Create relational objects `PropertyLotOwnership` for all currently assigned Property Lots.
     *
     */
    protected static function doGenerateHistory($self) {
        $self->read(['condo_id', 'date_from', 'date_to', 'property_lots_ids']);

        foreach($self as $id => $ownership) {
            foreach($ownership['property_lots_ids'] as $property_lot_id) {
                if(PropertyLotOwnership::search([
                        ['ownership_id', '=', $id],
                        ['property_lot_id', '=', $property_lot_id]
                    ])->count() <= 0
                ) {
                    PropertyLotOwnership::create([
                            'condo_id'          => $ownership['condo_id'],
                            'property_lot_id'   => $property_lot_id,
                            'ownership_id'      => $id,
                            'date_from'         => $ownership['date_from'],
                            'date_to'           => $ownership['date_to']
                        ]);
                }
            }
        }
    }

    /**
     * #memo - the communication preferences apply on the entire Ownership
     * and are used for communications with representative Owner
     *  - no preferences can be applied directly on Owner
     *  - only the representative can change the preferences
     */
    protected static function doGenerateCommunicationPrefs($self) {
        $self->read(['condo_id', 'name', 'has_external_representative', 'representative_identity_id', 'representative_owner_id' => ['identity_id']]);

        foreach($self as $id => $ownership) {

            $identity_id = null;
            $owner_id = null;
            $is_owner = false;

            if(!$ownership['has_external_representative']) {
                $is_owner = true;
            }

            if($is_owner) {
                $owner_id = $ownership['representative_owner_id']['id'] ?? null;
                $identity_id = $ownership['representative_owner_id']['identity_id'] ?? null;
            }
            else {
                $identity_id = $ownership['representative_identity_id'] ?? null;
            }

            // skip if no identity can be retrieved
            if(!$identity_id) {
                continue;
            }

            if(OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['ownership_id', '=', $id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', 'general_assembly_call']
                    ])->count() <= 0) {

                OwnershipCommunicationPreference::create([
                        'condo_id'                              => $ownership['condo_id'],
                        'ownership_id'                          => $id,
                        'identity_id'                           => $identity_id,
                        'owner_id'                              => $owner_id,
                        'communication_reason'                  => 'general_assembly_call',
                        'has_channel_email'                     => false,
                        'has_channel_postal'                    => false,
                        'has_channel_postal_registered'         => true,
                        'has_channel_postal_registered_receipt' => false
                    ]);
            }

            if(OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['ownership_id', '=', $id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', 'general_assembly_minutes']
                    ])->count() <= 0) {

                OwnershipCommunicationPreference::create([
                        'condo_id'                              => $ownership['condo_id'],
                        'ownership_id'                          => $id,
                        'identity_id'                           => $identity_id,
                        'owner_id'                              => $owner_id,
                        'communication_reason'                  => 'general_assembly_minutes',
                        'has_channel_email'                     => false,
                        'has_channel_postal'                    => true,
                        'has_channel_postal_registered'         => false,
                        'has_channel_postal_registered_receipt' => false
                    ]);
            }

            if(OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['ownership_id', '=', $id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', 'expense_statement']
                    ])->count() <= 0) {

                OwnershipCommunicationPreference::create([
                        'condo_id'                              => $ownership['condo_id'],
                        'ownership_id'                          => $id,
                        'identity_id'                           => $identity_id,
                        'owner_id'                              => $owner_id,
                        'communication_reason'                  => 'expense_statement',
                        'has_channel_email'                     => false,
                        'has_channel_postal'                    => true,
                        'has_channel_postal_registered'         => false,
                        'has_channel_postal_registered_receipt' => false
                    ]);
            }

            if(OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['ownership_id', '=', $id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', 'fund_request']
                    ])->count() <= 0) {

                OwnershipCommunicationPreference::create([
                        'condo_id'                              => $ownership['condo_id'],
                        'ownership_id'                          => $id,
                        'identity_id'                           => $identity_id,
                        'owner_id'                              => $owner_id,
                        'communication_reason'                  => 'fund_request',
                        'has_channel_email'                     => false,
                        'has_channel_postal'                    => true,
                        'has_channel_postal_registered'         => false,
                        'has_channel_postal_registered_receipt' => false
                    ]);
            }

            if(OwnershipCommunicationPreference::search([
                        ['condo_id', '=', $ownership['condo_id']],
                        ['ownership_id', '=', $id],
                        ['identity_id', '=', $identity_id],
                        ['communication_reason', '=', 'technical_communication']
                    ])->count() <= 0) {

                OwnershipCommunicationPreference::create([
                        'condo_id'                              => $ownership['condo_id'],
                        'ownership_id'                          => $id,
                        'identity_id'                           => $identity_id,
                        'owner_id'                              => $owner_id,
                        'communication_reason'                  => 'technical_communication',
                        'has_channel_email'                     => false,
                        'has_channel_postal'                    => true,
                        'has_channel_postal_registered'         => false,
                        'has_channel_postal_registered_receipt' => false
                    ]);
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

    protected static function doNormalizeRepresentativeOwner($self) {
        $self->read(['representative_owner_id', 'owners_ids']);
        foreach($self as $id => $ownership) {
            if(!$ownership['representative_owner_id']) {
                // by default, pick the first owner
                if(count($ownership['owners_ids']) > 0) {
                    self::id($id)->update(['representative_owner_id' => current($ownership['owners_ids'])]);
                }
            }
            else {
                // invalid/old owner
                if(!in_array($ownership['representative_owner_id'], $ownership['owners_ids'])) {
                    if(count($ownership['owners_ids']) > 0) {
                        self::id($id)->update(['representative_owner_id' => current($ownership['owners_ids'])]);
                    }
                    else {
                        self::id($id)->update(['representative_owner_id' => null]);
                    }
                }
            }
        }
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
            ->do('normalize_representative_owner')
            ->do('generate_accounts')
            ->do('generate_folders')
            ->do('generate_communication_prefs')
            ->do('generate_history');
    }

    protected static function calcPaymentReference($self) {
        $result = [];
        $self->read([
                'code',
                'condo_id' => ['code']
            ]);
        foreach($self as $id => $ownership) {
            if(!$ownership['code']) {
                continue;
            }
            if(!$ownership['condo_id']) {
                continue;
            }
            $reference =
                substr(str_pad((int) $ownership['condo_id']['code'], 6, '0', STR_PAD_LEFT), 0, 6) .
                substr(str_pad((int) $ownership['code'], 4, '0', STR_PAD_LEFT), 0, 4);

            $prefix = substr($reference, 0, 3);
            $suffix = substr($reference, 3);

            $result[$id] = self::computePaymentReference($prefix, $suffix);
        }
        return $result;
    }

    /**
     * Compute a Structured Reference using belgian SCOR (StructuredCommunicationReference) reference format.
     *
     * Note:
     *   format is aaa-bbbbbbb-XX and is displayed +++aaa/bbbb/bbbXX+++
     *   where Xaaa is the prefix, bbbbbbb is the suffix, and XX is the control number, that must verify (aaa * 10000000 + bbbbbbb) % 97
     *      since 10000000 % 97 = 76
     *      we do (aaa * 76 + bbbbbbb) % 97
     */
    private static function computePaymentReference($prefix, $suffix) {
        $a = intval($prefix);
        $b = intval($suffix);
        $control = ((76*$a) + $b ) % 97;
        $control = ($control == 0) ? 97 : $control;
        return sprintf("%03d%04d%03d%02d", $a, $b / 1000, $b % 1000, $control);
    }
}
