<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class PropertyLotOwnership extends \equal\orm\Model {

    public function getTable() {
        return 'realestate_ownership_ownership_rel_property_lot';
    }

    public static function getDescription() {
        return "This entity is meant to be used as a link between Ownerships and PropertyLots in order to be able to keep track of the changes (history) and to map several Property Lots to a same Ownership.";
    }

    public static function getColumns() {

        return [

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The  Property Lot the transfer file relates to.",
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "The date from which the ownership owns the property lot."
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "The date from which the ownership owned the property lot.",
            ]

        ];
    }
}
