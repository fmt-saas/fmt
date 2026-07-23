<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace fmt\import;

use documents\Document;
use documents\DocumentType;
use realestate\property\OwnershipTransfer;

class DataImport extends \equal\orm\Model {

    public static function getDescription() {
        return "DataImport is a technical staging entity used by import workflows to receive an uploaded structured data file,
            create the associated Document, select the import parser (banks, suppliers, condominium, ownership),
            and track validation/import status and logs before the final business objects are created.";
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'help'              => "At first, this value can be left to null (might be assigned manually or retrieved from document filename).",
                'foreign_object'    => 'realestate\property\Condominium',
                'visible'           => ['import_type', '=', 'condominium_import']
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the data import.',
                'required'          => true
            ],

            'data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateData'
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Targeted document of the import.',
                'onupdate'          => 'onupdateDocumentId'
            ],

            'import_type' => [
                'type'              => 'string',
                'selection'         => [
                    'banks_import',
                    'suppliers_import',
                    'condominium_import',
                    'ownership_import'
                ],
                'description'       => 'Targeted type of the import.',
                'required'          => true
            ],

            'logs' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => 'Human readable descriptor of the processing result.'
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The Ownership the property is being transferred to.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'visible'           => ['import_type', '=', 'ownership_import']
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'description'       => 'Optional link to the related bank statement.',
                'visible'           => ['import_type', '=', 'ownership_import']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'ready',
                    'failing',
                    'imported'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the processing (to avoid concurrent executions).',
                'help'              => "Remains `running` while all lines haven't been processed."
            ],

        ];
    }

    public static function getWorkflow() {
        return [
            'ready' => [
                'description' => 'The import document is ready.',
                'icon'        => 'draft',
                'transitions' => [
                    'mark_imported' => [
                        'description'   => 'Mark the document as imported.',
                        'onafter'       => 'onafterMarkImported',
                        'status'        => 'imported'
                    ]
                ]
            ]
        ];
    }

    protected static function onafterMarkImported($self) {
        $self->read(['import_type', 'ownership_transfer_id', 'ownership_id']);
        foreach($self as $id => $dataImport) {
            if($dataImport['import_type'] === 'ownership_import') {
                if($dataImport['ownership_transfer_id']) {
                    OwnershipTransfer::id($dataImport['ownership_transfer_id'])->update(['new_ownership_id' => 'ownership_id']);
                }
            }
        }
    }

    protected static function onupdateData($self) {
        $self->read(['name', 'data', 'import_type']);

        foreach($self as $id => $dataImport) {
            if(!$dataImport['data']) {
                continue;
            }
            $documentType = DocumentType::search(['code', '=', $dataImport['import_type']])->first();

            $document = Document::create([
                    'name'              => $dataImport['name'],
                    'data'              => $dataImport['data'],
                    'document_type_id'  => $documentType['id'],
                    'is_origin'         => true
                ])
                ->first();

            // remove current object data (pointless after successful import)
            self::id($id)->update([
                    'document_id'   => $document['id'],
                    'data' => null
                ]);
        }
    }


    // schedule the consistency check
    protected static function onupdateDocumentId($self) {
        $self->read(['document_id' => ['name']]);
        foreach($self as $id => $dataImport) {
            if(!$dataImport['document_id']) {
                continue;
            }
            // self::id($id)->update(['name' => 'Import ' . $dataImport['document_id']['name'] ]);
        }
    }

    /**
     * DataImport is used to upload and create a new Document.
     * We rely on the same strategy than regular Document upload, by receiving document meta from UI with onchange event.
     */
    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['data']['name'])) {
            $result['name'] = $event['data']['name'];
        }

        return $result;
    }


    public static function getActions() {
        return [
        /*
            'retry' => [
                'description'   => 'Request a new export attempt, by resetting status to `idle`.',
                'policies'      => [],
                'function'      => 'doRetry'
            ],
        */
        ];
    }

    /*
    protected static function doRetry($self) {
        $self->read(['status']);
        foreach($self as $id => $exportingTask) {
            if($exportingTask['status'] === 'failing') {
                self::id($id)->update(['status' => 'idle']);
            }
        }
    }
    */

}
