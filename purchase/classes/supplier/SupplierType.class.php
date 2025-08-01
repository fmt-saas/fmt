<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace purchase\supplier;

use equal\orm\Model;

class SupplierType extends Model {

    public static function getName() {
        return 'Supplier Type';
    }

    public static function getDescription() {
        return 'Supplier Types allow to categorize suppliers and apply specific rules, such as Labelling Rules, based on their type.';
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the Supplier type.",
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "The name of the supplier type.",
                'required'          => true
            ],

            'category' => [
                'type'              => 'string',
                'description'       => "Category of the supplier type.",
                'selection'         => [
                    'maintenance',
                    'utilities',
                    'works',
                    'equipment',
                    'services',
                    'finance',
                    'metering'
                ],
                'required'          => true
            ],

            'suppliers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'supplier_type_id',
                'description'       => "Supplier items relating to the Type."
            ]

        ];
    }


}
