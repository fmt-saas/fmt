<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferArrearLine extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership transfer the line relates to .",
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'due_date' => [
                'type'              => 'date',
                'usage'             => 'date/plain',
                'description'       => "Deadline before which the funding is expected."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Optional description to identify the funding.'
            ],

            'arrear_paragraph' => [
                'type'              => 'string',
                'description'       => 'Optional description to identify the funding.',
                'selection'         => [
                    '1',
                    '2'
                ],
                'required'          => true
            ],

            'arrear_line_type' => [
                'type'              => 'string',
                'selection'         => [
                    'funding',
                    'additional_provision',
                    'processing_fee'
                ],
                'required'          => true,
                'description'       => "Type of arrear the line refers to."
            ],

            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount expected for the funding.',
                'required'          => true,
                'dependents'        => ['name']
            ]

        ];
    }
}