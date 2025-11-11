<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class PropertyLotSuppliershipReference extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'property_lot_id' => [
                'type'              => 'many2one',
                'description'       => "The Property Lot that the Quota refers to.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'required'          => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'description'       => "The suppliership the email relates to, if any.",
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true
            ],

            'property_lot_ref' => [
                'type'              => 'string',
                'description'       => 'Arbitrary reference from the supplier for identifying the property lot.',
                'required'          => true
            ]

        ];

    }

}