<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;



class Bank extends \purchase\supplier\Supplier {

    public static function getName() {
        return 'Bank';
    }

    public static function getDescription() {
        return "A Bank is handled as a supplier with specific additional info.";
    }

    public static function getColumns() {

        return [
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

}
