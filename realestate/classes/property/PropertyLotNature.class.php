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
                'description'       => 'Name of the property lot nature.',
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the nature.',
                'multilang'         => true
            ],

            'hierarchy' => [
                'type'              => 'integer',
                'usage'             => 'number/integer{1,3}',
                /*
                    1 | main         | Includes properties used for living or business purposes, such as apartments, offices, and commercial spaces.
                    2 | dependency   | Covers auxiliary spaces like parking spots, garages, and storage units (cellars, basements).
                    3 | misc         | Encompasses other property types that do not fit into the first two categories.
                */
                'description'       => 'Hierarchy of the property lot nature.',
                'help'              => 'Hierarchy is used to prevent assigning a primary property lot as dependency of a secondary property lot.',
            ],

            'property_lots_ids' => [
                'type'              => 'one2many',
                'description'       => "The list of property lots assigned to this nature.",
                'foreign_object'    => 'realestate\property\PropertyLot',
                'foreign_field'     => 'nature_id'
            ],

            'count_property_lots' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCountPropertyLots',
                'description'       => 'Total amount of property lots assigned to this nature.',
                'store'             => true
            ],

        ];

    }

    public static function calcCountPropertyLots($self) {
        $result = [];
        $self->read(['property_lots_ids']);
        foreach($self as $id => $nature) {
            $result[$id] = count($nature['property_lots_ids'] ?? []);
        }
        return $result;
    }

}