<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\management;

class TaskCategoryRole extends \equal\orm\Model {

    public static function getName() {
        return 'Task Category Role';
    }

    public static function getDescription() {
        return 'Role visibility rule for a task category.';
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Display name of the visibility rule.',
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true
            ],

            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\TaskCategory',
                'description'       => 'Task category the rule applies to.',
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['name']
            ],

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'description'       => 'Employee role allowed to view the category.',
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['name', 'role_code']
            ],

            'role_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Code of the role allowed by the rule.',
                'relation'          => ['role_id' => 'code'],
                'store'             => true,
                'readonly'          => true
            ]
        ];
    }

    public function getUnique(): array {
        return [
            ['category_id', 'role_id']
        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['category_id' => ['name'], 'role_id' => ['name']]);
        foreach($self as $id => $rule) {
            $parts = [];
            if(isset($rule['category_id']['name'])) {
                $parts[] = $rule['category_id']['name'];
            }
            if(isset($rule['role_id']['name'])) {
                $parts[] = $rule['role_id']['name'];
            }
            $result[$id] = implode(' - ', $parts);
        }

        return $result;
    }
}