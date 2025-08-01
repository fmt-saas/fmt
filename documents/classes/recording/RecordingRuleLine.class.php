<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\recording;
use equal\orm\Model;

class RecordingRuleLine extends Model {

    public static function getName() {
        return "Recording Rule Line";
    }

    public static function getDescription() {
        return "Recording rules have one or more lines associating them with an account and a VAT rule.";
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => "The condominium the rule applies to.",
                'help'              => "If left unset, the rule applies at the agency/managing agent level"
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the recording rule line",
                'required'          => true
            ],

            'recording_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\recording\RecordingRule',
                'description'       => "Parent recording rule this line relates to."
            ],

            'account_code' => [
                'type'              => 'string',
                'description'       => "Code of the account to be associated to the Rule line.",
                'help'              => "This value depends on the intended use and should be defined as a template fo auto-assigning an account when a rule is created for a condominium.",
                'visible'           => ['condo_id', '=', null]
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Account',
                'description'       => "Accounting account the rule points to.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_control_account', '=', false], ['account_class', '=', '06']],
                'visible'           => ['condo_id', '<>', null]
            ],

            'apportionment_code' => [
                'type'              => 'string',
                'description'       => "Code of the default apportionment key to use (implied in retrieval of related apportionment_id).",
                'visible'           => ['condo_id', '=', null],
                'default'           => '0001'
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null], ['is_statutory', '=', false] ],
                'visible'           => ['condo_id', '<>', null]
            ],

            'owner_share'           => [
                'type'              => 'integer',
                'default'           => 100,
                'description'       => "Default value, in percent, of the amount to be imputed to the owner when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed."
            ],

            'tenant_share'          => [
                'type'              => 'integer',
                'default'           => 0,
                'description'       => "Default value, in percent, of the amount to be imputed to the tenant when using the account.",
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed."
            ],

            'share' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => "Share of the line, in percent (lines sum must be 100%).",
                'default'           => 1.0
            ]

        ];
    }


}