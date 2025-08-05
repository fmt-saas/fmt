<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\ownership;

class OwnershipCommunicationPreference extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the ownership belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'alias',
                'alias'             => 'communication_reason',
                'description'       => 'Name of the communication preference.'
            ],

            'communication_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'general_assembly_call',
                    'general_assembly_minutes',
                    'expense_statement',
                    'fund_request',
                    'technical_communication'
                ],
                'description'       => "Method used to send the invitation.",
                'required'          => true
            ],

            'communication_method' => [
                'type'              => 'string',
                'selection'         => [
                    'email',
                    'postal',
                    'postal_registered',
                    'postal_registered_receipt'
                ],
                'description'       => "Method used to send the invitation.",
                'default'           => 'postal_registered'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the owner refers to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'readonly'          => true
            ]

        ];
    }

    public function getUnique() {
        return [
            [ 'ownership_id', 'communication_reason' ]
        ];
    }
}