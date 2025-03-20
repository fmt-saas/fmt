<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

class FundRequestExecutionLine extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Execution';
    }

    public static function getDescription() {
        return "A Fund Request Execution Line represents an individual allocation within a Fund Request Execution, detailing the called amount for a specific owner and period.";
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'request_execution_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\funding\FundRequestExecution',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                // 'required'          => true,
                'readonly'          => true
            ],

            'called_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount requested for the related lo to co-owner.'
            ]

        ];
    }

}