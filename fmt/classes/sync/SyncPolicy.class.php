<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class SyncPolicy extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['object_class'],
                'store'             => true
            ],

            'object_class' => [
                'type'              => 'string',
                'usage'             => 'text/plain:128',
                'description'       => 'Targeted Entity.',
                'unique'            => true,
                'required'          => true,
                'dependents'        => ['name']
            ],

            'field_unique' => [
                'type'              => 'string',
                'description'       => "Field of the entity to use in order to check if object is unique (UUID assignment).",
                'required'          => true
            ],

            'sync_direction' => [
                'type'              => 'string',
                'usage'             => 'text/plain:30',
                'selection'         => [
                    // Local > Global
                    'ascending',
                    // Global > Local
                    'descending'
                ],
                'required'          => true,
                'description'       => 'Direction of the synchronization.'
            ],

            'last_pull' => [
                'type'              => 'datetime',
                'description'       => 'Last time some data were pulled from the global instance.',
                'default'           => 0,
                'visible'           => ['sync_direction', '=', 'descending']
            ],

            'scope' => [
                'type'              => 'string',
                'selection'         => [
                    // #memo - public is not relevant here (not synced)
                    // management on Local & Global
                    'protected',
                    // management on origin only (based sync direction : `ascending` = Local, `descending` = Global)
                    'private'
                ],
                'required'          => true,
                'description'       => 'Entity Control level - which instance has management.'
            ],

            'sync_policy_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\sync\SyncPolicyLine',
                'foreign_field'     => 'sync_policy_id',
                'description'       => 'Lines of the Update Policy.',
            ],

            'sync_policy_conditions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'fmt\sync\SyncPolicyCondition',
                'foreign_field'     => 'sync_policy_id',
                'description'       => 'Conditions of the Update Policy.',
            ],

        ];
    }

    public function getUnique() {
        return [
            ['object_class', 'sync_direction']
        ];
    }

    public static function canupdate($self, $values): array {
        $self->read(['sync_direction', 'scope']);
        foreach($self as $syncPolicy) {
            $sync_direction = $values['sync_direction'] ?? $syncPolicy['sync_direction'];
            $scope = $values['scope'] ?? $syncPolicy['scope'];

            if($sync_direction === 'ascending' && $scope === 'private') {
                return ['scope' => ['private_not_allowed_for_ascending' => 'Private scope isn\'t allowed for an ascending sync policy.']];
            }
        }

        return parent::canupdate($self, $values);
    }
}
