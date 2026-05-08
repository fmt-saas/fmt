<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
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

            'property_lot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\PropertyLot',
                'description'       => "The Property Lot the ownership file relates to.",
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\ownership\Ownership'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "The date from which the ownership owns the property lot.",
                'help'              => "By convention, this date must always be set. If unknown, it should be set to the date of the creation of the ownership (or 1970-01-01 if before).",
                'default'           => 0
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "The date from which the ownership owned the property lot.",
            ]

        ];
    }

    public function getIndexes(): array {
        return [
            ['ownership_id', 'property_lot_id']
        ];
    }
}
