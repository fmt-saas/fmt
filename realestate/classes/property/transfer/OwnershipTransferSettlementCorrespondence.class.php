<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property\transfer;

class OwnershipTransferSettlementCorrespondence extends \documents\correspondence\DocumentCorrespondence {

    public function getTable() {
        return 'realestate_property_transfer_settlement_correspondence';
    }

    public static function getColumns() {
        return [
            'settlement_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlement',
                'description'    => 'Settlement communicated to the seller or buyer.',
                'required'       => true,
                'readonly'       => true,
                'ondelete'       => 'cascade'
            ],

            'recipient_role' => [
                'type'        => 'string',
                'usage'       => 'text/plain:16',
                'selection'   => ['seller', 'buyer'],
                'description' => 'Role of the correspondence recipient in the ownership transfer.',
                'required'    => true,
                'readonly'    => true
            ],

            'has_document' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether the correspondence document was generated.',
                'default'     => false
            ],

            'is_acknowledged' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether the recipient acknowledged the correspondence.',
                'default'     => false
            ],

            'mails_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'core\Mail',
                'foreign_field'  => 'object_id',
                'domain'         => ['object_class', '=', 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence'],
                'visible'        => ['communication_method', '=', 'email']
            ]
        ];
    }

    public function getUnique() {
        return [
            ['settlement_id', 'recipient_role']
        ];
    }
}
