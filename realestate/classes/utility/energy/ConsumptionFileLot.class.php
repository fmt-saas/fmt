<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

use realestate\property\PropertyLot;

class ConsumptionFileLot extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'consumption_file_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\utility\energy\ConsumptionFile',
                'description'    => 'Consumption file the property lot reference belongs to.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'ondelete'       => 'cascade',
                'required'       => true
            ],

            'condo_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\Condominium',
                'description'    => 'Condominium the property lot reference belongs to.',
                'required'       => true,
                'readonly'       => true
            ],

            'property_lot_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'realestate\property\PropertyLot',
                'description'    => 'Property lot matched in the consumption file.',
                'domain'         => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]],
                'required'       => true
            ],

            'property_lot_extref' => [
                'type'        => 'string',
                'description' => 'External reference used by the supplier to identify the property lot.',
                'required'    => true
            ]
        ];
    }

    public function getUnique() {
        return [
            ['consumption_file_id', 'property_lot_extref']
        ];
    }

    public static function getActions() {
        return array_merge(parent::getActions(), [
            'remove' => [
                'description' => 'Remove the property lot reference from the consumption file.',
                'policies'    => [],
                'function'    => 'doRemove'
            ]
        ]);
    }

    protected static function doRemove($self) {
        $self->delete(true);
    }


    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['property_lot_id']) && $event['property_lot_id']) {
            $propertyLot = PropertyLot::id($event['property_lot_id'])
                ->read(['code'])
                ->first();
            if($propertyLot && $propertyLot['code']) {
                $result['property_lot_extref'] = $propertyLot['code'];
            }
        }
        return $result;
    }

}
