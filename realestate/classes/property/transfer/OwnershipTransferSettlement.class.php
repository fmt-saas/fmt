<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property\transfer;

class OwnershipTransferSettlement extends \equal\orm\Model {

    public function getTable() {
        return 'realestate_property_transfer_settlement';
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'        => 'computed',
                'result_type' => 'string',
                'description' => 'Technical name of the ownership transfer settlement.',
                'function'    => 'calcName',
                'store'       => true,
                'readonly'    => true
            ],

            'ownership_transfer_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\OwnershipTransfer',
                'description'    => 'Ownership transfer regularized by the settlement.',
                'required'       => true,
                'readonly'       => true,
                'ondelete'       => 'cascade',
                'dependents'     => ['name']
            ],

            'condo_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'description'    => 'Condominium concerned by the settlement.',
                'required'       => true,
                'readonly'       => true
            ],

            'seller_ownership_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\ownership\Ownership',
                'description'    => 'Ownership selling the transferred property lots.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'readonly'       => true
            ],

            'buyer_ownership_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\ownership\Ownership',
                'description'    => 'Ownership acquiring the transferred property lots.',
                'domain'         => ['condo_id', '=', 'object.condo_id'],
                'required'       => true,
                'readonly'       => true
            ],

            'transfer_date' => [
                'type'        => 'date',
                'description' => 'Economic date from which the buyer owns the property lots.',
                'required'    => true,
                'readonly'    => true
            ],

            'snapshot_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time when accounted sources were frozen.',
                'required'    => true,
                'readonly'    => true
            ],

            'accounting_date' => [
                'type'        => 'date',
                'description' => 'Date used to post the settlement miscellaneous operations.',
                'required'    => true,
                'default'     => fn() => strtotime('today')
            ],

            'status' => [
                'type'        => 'string',
                'selection'   => ['draft', 'validated', 'closed'],
                'description' => 'Current lifecycle status of the settlement.',
                'default'     => 'draft',
                'required'    => true,
                'readonly'    => true
            ],

            'calculation_version' => [
                'type'        => 'string',
                'description' => 'Version of the calculation rules used for the settlement.',
                'default'     => '1',
                'required'    => true,
                'readonly'    => true
            ],

            'calculation_hash' => [
                'type'        => 'string',
                'description' => 'Fingerprint of the complete calculation input and result.',
                'readonly'    => true
            ],

            'recalculated_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time of the latest draft recalculation.',
                'readonly'    => true
            ],

            'validated_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time at which the settlement was validated.',
                'readonly'    => true
            ],

            'closed_at' => [
                'type'        => 'datetime',
                'description' => 'Date and time at which the settlement was closed.',
                'readonly'    => true
            ],

            'seller_net_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Net amount credited to the seller; a negative value is debited.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'buyer_net_amount' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Net amount debited to the buyer; a negative value is credited.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'included_total' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Total amount of included settlement lines.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'excluded_total' => [
                'type'        => 'float',
                'usage'       => 'amount/money:2',
                'description' => 'Total amount of excluded settlement lines.',
                'default'     => 0.0,
                'readonly'    => true
            ],

            'has_accounted_sources' => [
                'type'        => 'boolean',
                'description' => 'Indicates whether relevant accounted sources were found.',
                'default'     => false,
                'readonly'    => true
            ],

            'alert_summary' => [
                'type'        => 'string',
                'usage'       => 'text/plain',
                'description' => 'Summary of the accounted sources requiring attention.',
                'readonly'    => true
            ],

            'lines_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementLine',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Detailed economic movements calculated for the settlement.'
            ],

            'operations_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementOperation',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Miscellaneous accounting operations generated by the settlement.'
            ],

            'correspondences_ids' => [
                'type'           => 'one2many',
                'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
                'foreign_field'  => 'settlement_id',
                'description'    => 'Seller and buyer correspondences generated for the settlement.'
            ],

            'logs' => [
                'type'        => 'string',
                'usage'       => 'text/plain',
                'description' => 'Technical log of settlement processing.',
                'readonly'    => true
            ]
        ];
    }

    public function getUnique() {
        return [
            ['ownership_transfer_id']
        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['ownership_transfer_id']);

        foreach($self as $id => $settlement) {
            $result[$id] = sprintf('Ownership transfer settlement #%d', $settlement['ownership_transfer_id']);
        }

        return $result;
    }
}
