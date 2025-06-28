<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
