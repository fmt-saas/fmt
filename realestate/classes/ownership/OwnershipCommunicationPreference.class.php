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
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true,
                'dependents'        => ['name', 'ownership_account_id']
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the communication preference.',
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code for identifying the kind of communication.',
                'unique'            => true
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
}