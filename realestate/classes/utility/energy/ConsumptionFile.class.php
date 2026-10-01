<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\utility\energy;

class ConsumptionFile extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => 'The condominium the consumption file relates to.',
                'foreign_object'    => 'realestate\property\Condominium',
                'readonly'          => true
            ],

            'suppliership_id' => [
                'type'              => 'many2one',
                'description'       => 'The suppliership the consumption file relates to.',
                'foreign_object'    => 'purchase\supplier\Suppliership',
                'domain'            => ['condo_id', '=', 'object.condo_id'],
                'required'          => true,
                'dependents'        => ['name']
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Code identifying the consumption file.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name composed of the supplier name and consumption file code.',
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true
            ]
        ];
    }

    protected static function calcName($self): array {
        $result = [];
        $self->read([
            'code',
            'suppliership_id' => [
                'supplier_id' => ['name']
            ]
        ]);

        foreach($self as $id => $consumption_file) {
            $supplier = $consumption_file['suppliership_id']['supplier_id'] ?? null;
            if(!$supplier || !$supplier['name'] || !$consumption_file['code']) {
                continue;
            }

            $result[$id] = sprintf('%s - %s', $supplier['name'], $consumption_file['code']);
        }

        return $result;
    }

}
