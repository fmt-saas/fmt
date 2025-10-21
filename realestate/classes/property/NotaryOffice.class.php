<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use purchase\supplier\SupplierType;

class NotaryOffice extends \purchase\supplier\Supplier {

    // #memo - NotaryOffice uses the same DB table as Supplier

    public static function getName() {
        return 'Notary Office';
    }

    public static function getDescription() {
        return "A Notary Office is handled as a supplier with specific additional info.";
    }

    public static function getColumns() {


/**
 * pour une étude, il peut y avoir plusieurs notaires (et ça peut changer)
 * pour une étude il peut y avoir plusieurs adresses
 * le nom de la rue peut être écrite de différentes manières et en plusieurs langues
 * le numéro de tél peut changer
 */
        return [
            // #memo - inherits uuid from Supplier

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current Identity.',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'realestate\property\NotaryOffice'
            ],

            'supplier_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\SupplierType',
                'description'       => "Suppliership items relating to the Supplier.",
                'dependents'        => ['supplier_type_code'],
                'default'           => 'defaultSupplierTypeId'
            ],

            'registry_ref' => [
                'type'              => 'string',
                'description'       => "Unique reference identifying the notary office.",
                'help'              => "For Belgium, we use the slug assigned by Fednot. References are formatted: `fednot:{notary-office-slug}`",
                'visible'           => ['object_class', '=', 'realestate\property\NotaryOffice']
            ]

        ];
    }


    protected static function defaultSupplierTypeId() {
        $result = null;
        $supplierType = SupplierType::search(['code', '=', 'notary_office'])->first();

        if($supplierType) {
            $result = $supplierType['id'];
        }

        return $result;
    }
}
