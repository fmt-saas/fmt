<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class PropertyLotNature extends \equal\orm\Model {


    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'List of employees assigned to the management of the condominium.',
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'List of employees assigned to the management of the condominium.',
                'multilang'         => true
            ],

            'hierarchy' => [
                'type'              => 'integer',
                // 1, 2, 3
                /*
                    main    | Includes properties used for living or business purposes, such as apartments, offices, and commercial spaces.
                    dependency   | Covers auxiliary spaces like parking spots, garages, and storage units (cellars, basements).
                    misc | Encompasses other property types that do not fit into the first two categories.
                */

                'description'       => 'List of employees assigned to the management of the condominium.',
            ]
        ];

    }

}