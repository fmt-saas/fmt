<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

class FundRequestExecutionLineEntry extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Execution Line Entry';
    }

    public static function getDescription() {
        return "A Fund Request Execution Line Entry represents an individual allocation within a Fund Request Execution Line,
        detailing the called amount for a specific property lot, for a given owner and period.";
    }

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'fund_request_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the line relates to.",
                'required'          => true,
                'readonly'          => true
            ],

            'request_execution_line_id' => [
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestExecutionLine',
                'description'       => 'The fund request execution (sale invoice) the line relates to.',
                'required'          => true,
                'readonly'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'readonly'          => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The property lot the entry lot relates to.",
                'required'          => true,
                'readonly'          => true
            ],

            'called_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line.',
                'required'          => true,
                'readonly'          => true
            ]

        ];
    }

}