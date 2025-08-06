<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

use purchase\supplier\SupplierType;

class NotaryOffice extends \purchase\supplier\Supplier {

    // #memo NotaryOffice uses the same DB table as Supplier

    public static function getName() {
        return 'Notary Office';
    }

    public static function getDescription() {
        return "A Notary Office is handled as a supplier with specific additional info.";
    }

    public static function getColumns() {

        return [
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
