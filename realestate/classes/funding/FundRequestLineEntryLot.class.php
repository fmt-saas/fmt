<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\funding;

class FundRequestLineEntryLot extends \equal\orm\Model {

    public static function getName() {
        return 'Fund Request Line Entry Lot';
    }

    public static function getDescription() {
        return "Represents the allocation of a fund request line at the property lot level.
            Each entry defines the shares and allocated amount for a specific lot, which are used to compute the total shares and allocated amount at the ownership level (FundRequestLineEntry).";
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
                'description'       => "Fund request the entry relates to."
            ],

            'request_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\funding\FundRequestLine',
                'description'       => "Fund request line the entry lot relates to."
            ],

            // #deprecated
            'ownership_id' => [
                'deprecated'        => 'ownership reference applies only to fund_request_line_entry',
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership'
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The property lot the entry lot relates to.",
            ],

            'apportionment_shares' => [
                'type'              => 'integer',
                'usage'             => 'amount/natural',
                'description'       => "Amount of shares the owner has for related apportionment, based on property lot.",
                'help'              => "The amount of shares the targeted property lot has for the apportionment."
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
                'description'       => 'Amount requested for the related property lot to the co-owner.',
                'onupdate'          => 'onupdateAllocatedAmount'
            ]

        ];
    }


    /**
     * Reset parent objects allocated_amount
     */
    public static function onupdateAllocatedAmount($self) {
        $result = [];
        $self->read(['line_entry_id', 'request_line_id', 'fund_request_id']);
        foreach($self as $id => $entryLot) {
            FundRequestLineEntry::id($entryLot['line_entry_id'])->update(['allocated_amount' => null]);
            FundRequestLine::id($entryLot['request_line_id'])->update(['allocated_amount' => null]);
            FundRequest::id($entryLot['fund_request_id'])->update(['allocated_amount' => null]);
        }
        return $result;
    }


}
