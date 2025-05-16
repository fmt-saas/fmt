<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the recording rule line",
                'required'          => true
            ],

            'account_code' => [
                'type'              => 'string',
                'description'       => "Code of the expense account associated to the Reserve Fund.",
                'required'          => true
            ],

            'recording_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\recording\RecordingRule',
                'description'       => "Parent recording rule this line relates to."
            ],

            'apportionment_id' => [
                'type'              => 'many2one',
                'description'       => "The key that the apportionment refers to.",
                'foreign_object'    => 'realestate\property\Apportionment',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['is_statutory', '=', false], ['is_active', '=', true], ['status', '=', 'published']],
                'help'              => "This value is used for splitting the amount amongst owners. One set, it can no longer be changed."
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