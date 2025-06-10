<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_control_account', '=', false]],
                'dependents'        => ['account_code']
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
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['journal_type', '=', 'MISC']],
                'store'             => true
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

}
