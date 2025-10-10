<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;
use realestate\property\PropertyLotOwnership;

class MiscOperationLine extends Model {

    public static function getName() {
        return "Miscellaneous Operation Line";
    }

    public static function getDescription() {
        return "A miscellaneous accounting operation can have one or more lines that are used to create related Accounting entry lines.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry line refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true,
                'default'           => 'defaultCondoId'
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Label for identifying the entry line.',
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the identifying the entry line.',
            ],

            'misc_operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\MiscOperation',
                'description'       => "Accounting entry the line relates to.",
                'ondelete'          => 'cascade',
                'dependents'        => ['journal_id']
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false]],
                'dependents'        => ['account_code', 'is_expense', 'is_income']
            ],

            'account_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code of the related accounting account.",
                'relation'          => ['account_id' => 'code'],
                'store'             => true
            ],

            'journal_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'finance\accounting\Journal',
                'description'       => "Accounting journal the entry relates to.",
                'relation'          => ['misc_operation_id' => 'journal_id'],
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['journal_type', '=', 'MISC']],
                'store'             => true,
                'instant'           => true
            ],

            'is_owner' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as a payment from/to an owner(ship).',
                'help'              => "When set to true, the line implies a link with a Funding.",
                'function'          => 'calcIsOwner',
                'store'             => true,
                'instant'           => true
            ],

            'is_expense' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as an unexpected expense or income.',
                'help'              => "When set to true, the line implies a stand alone purchase operation.",
                'function'          => 'calcIsExpense',
                'store'             => true,
                'instant'           => true
            ],

            'is_income' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the line as an unexpected expense or income.',
                'help'              => "When set to true, the line implies a stand alone sale operation.",
                'function'          => 'calcIsIncome',
                'store'             => true,
                'instant'           => true
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'validated']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [
                    [['account_id', '<>', null], ['is_expense', '=', true]],
                    [['account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [
                    [['account_id', '<>', null], ['is_expense', '=', true]],
                    [['account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'visible'           => [
                    [['account_id', '<>', null], ['is_expense', '=', true]],
                    [['account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'vat_rate' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'default'           => 0.0,
                'visible'           => [
                    [['account_id', '<>', null], ['is_expense', '=', true]],
                    [['account_id', '<>', null], ['is_income', '=', true]]
                ]
            ],

            'is_private_expense' => [
                'type'              => 'boolean',
                'description'       => 'Enable to apply charge to a single owner.',
                'default'           => false,
                'onupdate'          => 'onupdateIsPrivateExpense'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the line refers to (based on accounting account).",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'visible'           => ['is_private_expense', '=', true],
                'ondelete'          => 'cascade',
                'domain'            => [['condo_id', '=', 'object.condo_id']]
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "Property Lot to apply the charge to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'visible'           => ['is_private_expense', '=', true],
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be debited on the account.',
                'default'           => 0.0
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be credited on the account.',
                'default'           => 0.0
            ]

        ];
    }

    // description de la MiscOperation
    protected static function oncreate($self, $values, $lang) {
        if(isset($values['misc_operation_id'])) {
            $miscOperation = MiscOperation::id($values['misc_operation_id'])
                ->read(['description'])
                ->first();
            $self->update(['description' => $miscOperation['description']], $lang);
        }
    }

    protected static function calcIsExpense($self) {
        $result = [];
        $self->read(['account_id']);
        foreach($self as $id => $miscOperationLine) {
            $result[$id] = self::computeIsExpense($miscOperationLine['account_id']);
        }
        return $result;
    }

    protected static function calcIsIncome($self) {
        $result = [];
        $self->read(['account_id']);
        foreach($self as $id => $miscOperationLine) {
            $result[$id] = self::computeIsIncome($miscOperationLine['account_id']);
        }
        return $result;
    }

    protected static function calcOwnershipId($self) {
        $result = [];
        $self->read(['condo_id', 'is_owner', 'account_id' => ['ownership_id']]);
        foreach($self as $id => $miscOperationLine) {
            if(!$miscOperationLine['is_owner'] || !$miscOperationLine['account_id']['ownership_id']) {
                continue;
            }
            $result[$id] = $miscOperationLine['account_id']['ownership_id'];
        }
        return $result;
    }

    protected static function calcIsOwner($self) {
        $result = [];
        $self->read(['account_id']);
        foreach($self as $id => $miscOperationLine) {
            $result[$id] = self::computeIsOwner($miscOperationLine['account_id']);
        }
        return $result;
    }

    private static function computeIsIncome($account_id) {
        $result = false;
        if($account_id) {
            $account = Account::id($account_id)->read(['code'])->first();
            if($account) {
                $account_class_digit = substr($account['code'], 0, 1);
                $result = ($account_class_digit === '7');
            }
        }
        return $result;
    }

    private static function computeIsExpense($account_id) {
        $result = false;
        if($account_id) {
            $account = Account::id($account_id)->read(['code'])->first();
            if($account) {
                $account_class_digit = substr($account['code'], 0, 1);
                $result = ($account_class_digit === '6');
            }
        }
        return $result;
    }

    private static function computeIsOwner($account_id) {
        $result = false;
        if($account_id) {
            $account = Account::id($account_id)->read(['code'])->first();
            if($account) {
                $account_class_digits_two = substr($account['code'], 0, 2);
                $result = ($account_class_digits_two === '41');
            }
        }
        return $result;
    }

    protected static function onupdateIsPrivateExpense($self) {
        $self->read(['is_private_expense', 'condo_id']);
        foreach($self as $id => $miscOperationLine) {
            if(!$miscOperationLine['is_private_expense']) {
                continue;
            }
            // set expense_account_id to 643xxx
            $account = Account::search([
                    ['condo_id', '=', $miscOperationLine['condo_id']],
                    ['operation_assignment', '=', 'private_expenses']
                ])
                ->first();
            if($account) {
                self::id($id)->update(['account_id' => $account['id']]);
            }
        }
    }

public static function onchange($event, $values, $view) {
        $result = [];

        // check VAT
        if(isset($event['vat_rate']) && $event['vat_rate'] >= 1) {
            $result['vat_rate'] = round($event['vat_rate'] / 100, 2);
            $event['vat_rate'] = $result['vat_rate'];
        }

        // update expense account
        if(isset($event['is_private_expense']) && $event['is_private_expense']) {
            $account = Account::search([['condo_id', '=', $values['condo_id']], ['operation_assignment', '=', 'private_expenses']])
                ->read(['id', 'name'])
                ->first();
            if($account) {
                $result['expense_account_id'] = [
                        'id'    => $account['id'],
                        'name'  => $account['name']
                    ];
            }
        }

        if(isset($event['tenant_share'])) {
            $result['owner_share'] = 100 - intval($event['tenant_share']);
        }
        elseif(isset($event['owner_share'])) {
            $result['tenant_share'] = 100 - intval($event['owner_share']);
        }

        // synchronize ownership & property lots
        // #memo - we must be able to assign any ownership (not only active ones)
        if(array_key_exists('ownership_id', $event)) {
            if($event['ownership_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['ownership_id', '=', $event['ownership_id']]])->read(['property_lot_id'])->get(true);
                $property_lots_ids = array_map(function ($a) {return $a['property_lot_id'];}, $propertyOwnerships);
                if(!$values['property_lot_id'] || !in_array($values['property_lot_id'], $property_lots_ids) ) {
                    $result['property_lot_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $property_lots_ids]]
                    ];
                }
            }
            else {
                $result['ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }
        if(array_key_exists('property_lot_id', $event)) {
            if($event['property_lot_id']) {
                $propertyOwnerships = PropertyLotOwnership::search([['property_lot_id', '=', $event['property_lot_id']]])->read(['ownership_id'])->get(true);
                $ownerships_ids = array_map(function ($a) {return $a['ownership_id'];}, $propertyOwnerships);
                if(!$values['ownership_id'] || !in_array($values['ownership_id'], $ownerships_ids) ) {
                    $result['ownership_id'] = [
                        'domain' => [['condo_id', '=', $values['condo_id']], ['id', 'in', $ownerships_ids]]
                    ];
                }
            }
            else {
                $result['ownership_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
                $result['property_lot_id'] = [
                    'domain' => ['condo_id', '=', $values['condo_id']]
                ];
            }
        }
        if(isset($event['account_id'])) {
            $account = Account::id($event['account_id'])->read(['id', 'apportionment_id', 'tenant_share', 'owner_share'])->first();
            if($account) {
                $result['apportionment_id'] = $account['apportionment_id'];
                $result['tenant_share'] = $account['tenant_share'];
                $result['owner_share'] = $account['owner_share'];
            }
        }
        return $result;
    }

}
