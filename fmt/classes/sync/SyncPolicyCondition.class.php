<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class SyncPolicyCondition extends Model {

    public static function getColumns() {
        return [

            'sync_policy_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'fmt\sync\SyncPolicy',
                'description'       => 'Reference to the parent sync policy.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'function'          => 'calcName'
            ],

            'operand' => [
                'type'              => 'string',
                'required'          => true
            ],

            'operator' => [
                'type'              => 'string',
                'selection'         => ['=', '>', '>=', '<', '<=', '<>', 'in', 'not_in'],
                'required'          => true
            ],

            'value' => [
                'type'              => 'string',
                'required'          => true
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['operand', 'operator', 'value']);
        foreach($self as $id => $syncPolicyCondition) {
            $result[$id] = $syncPolicyCondition['operand'].' '.$syncPolicyCondition['operator'].' '.$syncPolicyCondition['value'];
        }

        return $result;
    }
}
