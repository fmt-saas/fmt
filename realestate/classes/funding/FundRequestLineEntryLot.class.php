<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\funding;

class FundRequestLineEntryLot extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Line Entry Lot';
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
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequest',
                'description'       => "Fund request the entry relates to.",
                'relation'          => ['line_entry_id' => ['request_line_id' => 'fund_request_id']],
                'store'             => true
            ],

            'request_line_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestLine',
                'description'       => "Fund request line the entry lot relates to.",
                'relation'          => ['line_entry_id' => 'request_line_id'],
                'store'             => true
            ],

            'ownership_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'relation'          => ['line_entry_id' => 'ownership_id'],
                'store'             => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The property lot the entry lot relates to.",
            ],

            'line_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestLineEntry',
                'description'       => "The fund request line the lot relates to.",
                'dependents'        => ['fund_request_id', 'request_line_id', 'ownership_id'],
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'allocated_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount requested for the related lo to co-owner.',
                // #memo - this is done in parent FundRequest
                // 'dependents'        => ['line_entry_id' => ['allocated_amount', 'request_line_id' => ['allocated_amount', 'fund_request_id' => ['allocated_amount']]]]
            ]

        ];
    }

}
