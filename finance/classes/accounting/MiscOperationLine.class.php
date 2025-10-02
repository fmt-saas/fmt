<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\accounting;
use equal\orm\Model;

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

            'ownership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcOwnershipId',
                'store'             => true,
                'instant'           => true,
                'description'       => "The ownership that the funding refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'ondelete'          => 'cascade',
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'visible'           => [['is_owner', '=', true]]
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

}
