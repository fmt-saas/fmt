<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\data\DataGenerator;

class Bank extends \purchase\supplier\Supplier {

    // #memo - Bank uses the same DB table as Supplier

    public static function constants() {
        return ['FMT_INSTANCE_TYPE'];
    }

    public static function getName() {
        return 'Bank';
    }

    public static function getDescription() {
        return "A Bank is handled as a supplier with specific additional info.";
    }

    public static function getColumns() {

        return [
            // #memo - inherits uuid from Supplier

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current Identity.',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'finance\bank\Bank'
            ],

            'bic' => [
                'type'              => 'string',
                'description'       => 'Official BIC/Swift code of the Bank.',
                'required'          => true
            ]

        ];
    }

    /**
     * This is a "private class": upon creation, assign a unique UUID if on GLOBAL instance
     */
    protected static function oncreate($self, $orm) {
        foreach($self as $id => $object) {
            if(constant('FMT_INSTANCE_TYPE') === 'global') {
                do {
                    $uuid = DataGenerator::uuid();
                    $existing = $orm->search(static::class, ['uuid', '=', $uuid]);
                } while( $existing > 0 && count($existing) > 0 );

                self::id($id)->update(['uuid' => $uuid]);
            }
        }
    }

}
