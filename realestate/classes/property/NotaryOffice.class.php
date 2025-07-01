<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

use purchase\supplier\SupplierType;

class NotaryOffice extends \purchase\supplier\Supplier {

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
