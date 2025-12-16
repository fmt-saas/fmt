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
                'type'              => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                // #memo - some exports may be organisation-wide (e.g., reports)
                // 'required'          => true
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the task, as set at creation.',
                'required'          => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the object object_id points to.'
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the object the email originates from.'
            ],

            'exporting_task_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\export\ExportingTaskLine',
                'foreign_field'     => 'exporting_task_id',
                'description'       => 'Lines of the task.'
            ],

            'download_link' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url.relative',
                'description'       => 'URL for downloading the export.',
                'function'          => 'calcDownloadLink',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['status', '=', 'ready']
            ],

            'logs_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\export\ExportingTaskLog',
                'foreign_field'     => 'task_id'
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the export as downloaded by the user.',
                'default'           => false
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
                'description'       => 'Current status of the processing (to avoid concurrent executions).',
                'help'              => "Remains `running` while all lines haven't been processed."
            ],

        ];
    }

    public static function getActions() {
        return [
            'retry' => [
                'description'   => 'Request a new export attempt, by resetting status to `idle`.',
                'policies'      => [],
                'function'      => 'doRetry'
            ]
        ];
    }

    protected static function doRetry($self, $dispatch) {
        $self->read(['status', 'exporting_task_lines_ids']);
        foreach($self as $id => $exportingTask) {
            if($exportingTask['status'] === 'failing') {
                self::id($id)->update(['status' => 'idle']);
                ExportingTaskLine::ids($exportingTask['exporting_task_lines_ids'])->update(['status' => 'idle']);
            }
            $dispatch->cancel('documents.export.export_failing', 'documents\export\ExportingTask', $id);
        }
    }

    protected static function calcDownloadLink($self) {
        $result = [];
        $self->read(['status']);
        foreach($self as $id => $exportingTask) {
            if($exportingTask['status'] !== 'ready') {
                continue;
            }
            // #memo - `download` controller groups all documents from lines into a single .zip archive
            $result[$id] = '/?get=documents_export_ExportingTask_download&id=' . $id;
        }
        return $result;
    }

}
