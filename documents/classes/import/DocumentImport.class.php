<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace documents\import;

use documents\Document;
use documents\DocumentType;
use equal\orm\Model;
use purchase\supplier\Suppliership;

// Ephemeral entity used by the front-end to import historical condominium documents into the EDMS.
// It creates the actual Document after upload, validates the metadata and lets Document hooks place it in the right folder.
class DocumentImport extends Model {

    public static function getName() {
        return 'Document import';
    }

    public static function getDescription() {
        return 'Document Import is a technical staging entity used to upload a document with EDMS metadata, create the final Document, and remove the temporary import record.';
    }


    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'help'              => "At first, this value can be left to null (might be assigned manually or retrieved from document filename).",
                'foreign_object'    => 'realestate\property\Condominium',
                'onupdate'          => 'onupdateCondoId',
                'dependents'        => ['suppliership_id']
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that the document relates to, if any.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'onupdate'          => 'onupdateOwnershipId',
                'domain'            => ['condo_id', '=', 'object.condo_id']
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'description'       => "The supplier the document originates from.",
                'foreign_object'    => 'purchase\supplier\Supplier',
                'dependents'        => ['suppliership_id'],
            ],

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the imported document.',
                'required'          => true
            ],

            'data' => [
                'type'              => 'binary',
                'description'       => 'Raw binary data of the uploaded document',
                'help'              => 'This field is meant to be used for the subsequent document creation, and is emptied once the document creation is confirmed.',
                'onupdate'          => 'onupdateData'
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Document type associated with the document.',
                'onupdate'          => 'onupdateDocumentTypeId',
                'domain'            => ['code', 'not in', ['invoice', 'bank_statement']],
                'dependents'        => ['document_type_code']
            ],

            'document_subtype_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentSubtype',
                'description'       => 'Document subtype associated with the document, if any.',
                'domain'            => ['document_type_id', '=', 'object.document_type_id'],
                'onupdate'          => 'onupdateDocumentSubtypeId',
                'dependents'        => ['document_subtype_code']
            ],

            'document_type_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_type_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'document_subtype_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['document_subtype_id' => 'code'],
                'store'             => true,
                'instant'           => true
            ],

            'document_visibility' => [
                'type'              => 'string',
                'selection'         => [
                    'public',       // visible to all condo owners + syndic
                    'protected',    // visible only to a single owner (to which the document is linked) + syndic
                    'private'       // visible only to syndic
                ],
                'default'           => 'private',
                'onupdate'          => 'onupdateDocumentVisibility',
                'description'       => 'Defines who can access the document.',
                'help'              => 'This field is synchronized with the node and updates automatically when the parent node visibility changes.'
            ],

        ];
    }


    protected static function onupdateData($self) {
        $self->read(['condo_id', 'name', 'data', 'document_type_id', 'document_subtype_id', 'document_visibility', 'ownership_id', 'supplier_id']);

        foreach($self as $id => $documentImport) {
            if(!isset($documentImport['data']) || $documentImport['data'] === null || $documentImport['data'] === '') {
                continue;
            }

            if($documentImport['supplier_id']) {
                $suppliership = Suppliership::search([
                        ['condo_id', '=', $documentImport['condo_id']],
                        ['supplier_id', '=', $documentImport['supplier_id']]
                    ])
                    ->first();
                if(!$suppliership) {
                    Suppliership::create([
                            'condo_id'      => $documentImport['condo_id'],
                            'supplier_id'   => $documentImport['supplier_id']
                        ])
                        ->first();
                }
            }

            $document = Document::create([
                    'condo_id'              => $documentImport['condo_id'],
                    'name'                  => $documentImport['name'],
                    'data'                  => $documentImport['data'],
                    'is_origin'             => true
                ])
                ->update([
                    'document_type_id'      => $documentImport['document_type_id'],
                    'document_subtype_id'   => $documentImport['document_subtype_id']
                ])
                ->first();

            if($documentImport['supplier_id']) {
                Document::id($document['id'])->update(['supplier_id' => $documentImport['supplier_id']]);
            }

            if($documentImport['ownership_id']) {
                Document::id($document['id'])->update(['ownership_id' => $documentImport['ownership_id']]);
            }
            if($documentImport['document_visibility']) {
                Document::id($document['id'])->update(['document_visibility' => $documentImport['document_visibility']]);
            }

            // Remove current import object after successful import.
            self::id($id)->delete(true);
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


    protected static function canupdate($self, $values) {
        $result = [];
        $self->read(['condo_id', 'ownership_id']);
        foreach ($self as $id => $documentImport) {

            if(!array_key_exists('condo_id', $values) || !$values['condo_id']) {
                if(!$documentImport['condo_id']) {
                    $result[$id] = [
                        'missing_condo_id' => "A condominium must be set."
                    ];
                    continue;
                }
            }

            if(array_key_exists('document_type_id', $values)) {

                $excluded_document_types_ids = DocumentType::search(['code', 'in', ['invoice', 'bank_statement']])->ids();

                if(in_array($values['document_type_id'], $excluded_document_types_ids)) {
                    if(!$ownership_id) {
                        $result[$id] = [
                            'forbidden_document_type' => "This type of document cannot be uploaded this way."
                        ];
                        continue;
                    }
                }

                if(in_array($values['document_type_id'], ['expense_statement', 'fund_request'])) {
                    $ownership_id = $values['ownership_id'] ?? $documentImport['ownership_id'] ?? null;
                    if(!$ownership_id) {
                        $result[$id] = [
                            'missing_ownership_id' => "Ownership is mandatory for this kind of document."
                        ];
                        continue;
                    }
                }
            }
        }

        return $result;
    }



}
