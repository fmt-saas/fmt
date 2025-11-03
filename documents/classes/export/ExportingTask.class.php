<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace documents\export;

class ExportingTask extends \equal\orm\Model {

    /**
     * 	Tasks are executed by the CRON service if moment (timestamp) is lower or equal to the current time
     */
    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'relation'          => ['document_id' => 'condo_id'],
                'store'             => true,
                'instant'           => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the task, as set at creation.',
                'required'          => true
            ],

            'exporting_task_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\export\ExportingTaskLine',
                'foreign_field'     => 'exporting_task_id',
                'description'       => 'Lines of the task.'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'idle',
                    'running',
                    'ready'
                ],
                'default'           => 'idle',
                'description'       => 'Current status of the processing (to avoid concurrent executions).',
                'help'              => "Remains `running` while all lines haven't been processed."
            ],


        ];
    }

}
