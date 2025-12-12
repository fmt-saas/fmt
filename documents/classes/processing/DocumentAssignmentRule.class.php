<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\processing;

use equal\orm\Model;

class DocumentAssignmentRule extends Model {

    public static function getName() {
        return "Document Assignment Rule";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Short description of the assignment rule.',
                'function'          => 'calcName',
                'readonly'          => true
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type this rule applies to.',
                'required'          => true,
                'dependents'        => ['name', 'document_type_code']
            ],

            'document_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_type_id' => 'code'],
                'description'       => 'Document type code this rule applies to.',
                'store'             => true,
                'instant'           => true
            ],

            'process_step' => [
                'type'              => 'string',
                'selection'         => [
                    'created',
                    'assigned',
                    'completed',
                    'validated',
                    'integrated'
                ],
                'description'       => 'DocumentProcess step at which the assignment applies.',
                'dependents'        => ['name'],
                'required'          => true
            ],

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'description'       => 'Role to which the document should be assigned.',
                'dependents'        => ['name', 'role_code'],
                'required'          => true
            ],

            'role_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['role_id' => 'code'],
                'description'       => 'Role code this rule applies to.',
                'store'             => true,
                'instant'           => true
            ],

        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['state', 'document_type_id' => ['name'], 'role_id' => ['name'], 'process_step']);
        foreach($self as $id => $rule) {
            if($rule['state'] === 'draft') {
                continue;
            }
            $result[$id] = $rule['document_type_id']['name'] . " - " .
                ucfirst($rule['process_step']) . " -> " .
                $rule['role_id']['name'];
        }
        return $result;
    }

    public function getUnique(): array {
        return [
            ['document_type_id', 'process_step', 'role_id']
        ];
    }
}
