<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace purchase\supplier;

class SuppliershipContract extends \equal\orm\Model {

    public static function getName() {
        return 'Supplier Contract';
    }


    public static function getDescription() {
        return 'A contract describes a service delivery contracted by a condominium with a supplier.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Name identifying the contract, if any."
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
                'required'          => true,
                'dependents'        => ['supplier_id']
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Start of validity period.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "End of validity period.",
                'help'              => "Duration can either be set in advance or keep running while not statuted by the general assembly. This is a theoretical date: the contract can be revoked by a general assembly.",
                'required'          => true
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Current state of the contract.",
                'default'           => true
            ],

            'contract_ref' => [
                'type'              => 'string',
                'description'       => "Specific reference of the contract provided by the supplier.",
                'default'           => true
            ]

        ];
    }

}

