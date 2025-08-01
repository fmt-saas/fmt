<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace purchase\supplier;

class SuppliershipReference extends \equal\orm\Model {

    public static function getName() {
        return 'Supplier Reference';
    }

    public static function getDescription() {
        return 'A Supplier Reference is an assignment of a specific value by a supplier to a Condominium in a given context.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'supplier_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'description'       => "Supplier the contract relates to.",
                'relation'          => ['suppliership_id' => 'supplier_id'],
                'store'             => true,
                'instant'           => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'required'          => true
            ],

            'is_unique' => [
                'type'              => 'boolean',
                'description'       => "Flag marking the reference as unique.",
                'default'           => false
            ],

            'reference_type' => [
                'type'              => 'string',
                'selection'         => [
                    'installation_number',
                    'ean_number',
                    'meter_number',
                    'customer_number',
                    'phone_number',
                    'elevator_number',
                    'building_code'
                ],
                'description'       => "Type of reference assigned by the supplier.",
                'required'          => true
            ],

            'reference_value' => [
                'type'              => 'string',
                'description'       => "Specific reference assigned to the customer by the supplier.",
                'required'          => true
            ]

        ];
    }

}

