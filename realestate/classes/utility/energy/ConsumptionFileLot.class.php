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
            ['consumption_file_id', 'property_lot_id']
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
        $self->delete();
    }

    protected static function oncreate($self) {
        try {
            self::assertSameCondo($self);
        }
        catch(\Throwable $throwable) {
            $self->delete(true);
            throw $throwable;
        }
    }

    protected static function onbeforeupdate($self, $values) {
        self::assertSameCondo($self, $values);
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

    private static function assertSameCondo($self, array $values = []) {
        $self->read(['condo_id', 'consumption_file_id', 'property_lot_id']);
        foreach($self as $fileLot) {
            $condo_id = self::relationId($values['condo_id'] ?? $fileLot['condo_id']);
            $consumption_file_id = self::relationId($values['consumption_file_id'] ?? $fileLot['consumption_file_id']);
            $property_lot_id = self::relationId($values['property_lot_id'] ?? $fileLot['property_lot_id']);

            $file = ConsumptionFile::id($consumption_file_id)->read(['condo_id'])->first();
            if(!$file || $file['condo_id'] !== $condo_id) {
                throw new \Exception('file_condo_mismatch', EQ_ERROR_INVALID_PARAM);
            }

            $propertyLot = PropertyLot::id($property_lot_id)->read(['condo_id'])->first();
            if(!$propertyLot || $propertyLot['condo_id'] !== $condo_id) {
                throw new \Exception('property_lot_condo_mismatch', EQ_ERROR_INVALID_PARAM);
            }
        }
    }

    private static function relationId($value) {
        if(is_array($value)) {
            return $value['id'] ?? null;
        }
        return $value;
    }
}
