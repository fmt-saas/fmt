<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\tax;
use equal\orm\Model;

class VatRule extends Model {

    public static function getName() {
        return "VAT Rule";
    }

    public static function getDescription() {
        return "VAT rules allow to specify which VAT rate applies for a given kind of operation.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the VAT rule.",
                'multilang'         => true,
                'required'          => true
            ],

            'rate' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => "Rate of the VAT rule.",
                'required'          => true
            ],

            'vat_rule_type' => [
                'type'              => 'string',
                'description'       => "Kind of operation this rule relates to.",
                'selection'         => [
                        'purchase',
                        'sale'
                    ],
                'required'          => true
            ],

            'account_code' => [
                'type'              => 'string',
                'description'       => "Code of the account the tax amount relates to.",
            ],

            // #deprecated - there can be several charts of accounts
            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Account which the tax amount relates to.",
            ]

        ];
    }

}