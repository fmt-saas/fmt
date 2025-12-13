<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace documents\export;

class ExportingTaskLine extends \equal\orm\Model {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the task, as set at creation.',
                'required'          => true
            ],

            'exporting_task_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\export\ExportingTask',
                'description'       => 'Parent exporting task.',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'pid' => [
                'type'              => 'integer',
                'description'       => 'Process Identifier of the script running the task.',
                'visible'           => ['status', '=', 'running']
            ],

            'controller' => [
                'type'              => 'string',
                'description'       => "Full notation of the action controller to invoke (ex. core_example_action)."
            ],

            'params' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "JSON object holding the parameters to relay to the controller.",
                'default'           => '{}'
            ],

            'document_id' => [
                'type'              => 'many2one',
                'description'       => 'The document (PDF) of the invitation, if any.',
                'foreign_object'    => 'documents\Document',
                'onupdate'          => 'onupdateDocumentId',
                'visible'           => [['status', '=', 'ready']]
            ],

            'logs_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\export\ExportingTaskLog',
                'foreign_field'     => 'task_line_id'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'idle',
                    'running',
                    'ready',
                    'failing'
                ],
                'default'           => 'idle',
                'description'       => 'Current status of the processing (to avoid concurrent executions).'
            ]

        ];
    }

}
