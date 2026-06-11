<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace documents\import;

use documents\Document;
use documents\DocumentSubtype;
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
                'dependents'        => ['suppliership_id'],
                'required'          => true
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
                'domain'            => ['code', 'not in', ['supplier_invoice', 'bank_statement']],
                'dependents'        => ['document_type_code'],
                'required'          => true
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
                    'condo',        // visible to all owners of a same condo + syndic
                    'ownership',    // visible to all owners of a same ownership + syndic
                    'owner',        // visible only to a single owner or supplier
                    'suppliership', // visible to a specific supplier of a condo + syndic
                    'agency'        // visible only to syndic (employees)
                ],
                'default'           => 'agency',
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
                    'document_type_id'      => $documentImport['document_type_id']
                ])
                ->update([
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

        if(isset($event['supplier_id'])) {
            $condo_id = $event['condo_id'] ?? $values['condo_id'] ?? null;
            $suppliership = Suppliership::search([['condo_id', '=', $condo_id], ['supplier_id', '=', $event['supplier_id']]])->first();
            $result['suppliership_id'] = $suppliership['id'] ?? null;
        }

        // gestion du change de type de document -> visibility
        if(isset($event['document_type_id'])) {
            $documentType = DocumentType::id($event['document_type_id'])->read(['document_visibility'])->first();
            if($documentType) {
                $result['document_visibility'] = $documentType['document_visibility'];
            }
        }

        if(isset($event['document_subtype_id'])) {
            $documentSubtype = DocumentSubtype::id($event['document_subtype_id'])->read(['document_visibility'])->first();
            if($documentSubtype) {
                $result['document_visibility'] = $documentSubtype['document_visibility'];
            }
        }

        return $result;
    }

    public static function getPolicies(): array {
        return [
            'is_valid' => [
                'description' => 'Checks that the accounting entry line is reversed before detaching its matching.',
                'function'    => 'policyIsValid'
            ]
        ];
    }

    protected static function canupdate($self, $values) {
        $result = [];
        $self
            ->assert('is_valid')
            ->read(['condo_id', 'ownership_id', 'suppliership_id', 'document_type_id', 'document_visibility']);

        foreach ($self as $id => $documentImport) {

            if(!array_key_exists('condo_id', $values) || !$values['condo_id']) {
                if(!$documentImport['condo_id']) {
                    $result[$id] = [
                        'missing_condo_id' => "A condominium must be set."
                    ];
                    continue;
                }
            }

            $document_visibility = $values['document_visibility'] ?? $documentImport['document_visibility'] ?? 'agency';

            if($document_visibility === 'ownership') {
                $ownership_id = $values['ownership_id'] ?? $documentImport['ownership_id'] ?? null;

                if(!$ownership_id) {
                    return [
                        'missing_visibility_target' => "Ownership is mandatory for `ownership` documents."
                    ];
                }
            }
            elseif($document_visibility === 'suppliership') {

                $suppliership_id = $values['suppliership_id'] ?? $documentImport['suppliership_id'] ?? null;

                if(!$suppliership_id) {
                    return [
                        'missing_visibility_target' => "Suppliership is mandatory for `suppliership` documents."
                    ];
                }
            }

            if(array_key_exists('document_type_id', $values)) {

                $excluded_document_types_ids = DocumentType::search(['code', 'in', ['supplier_invoice', 'bank_statement']])->ids();

                if(in_array($values['document_type_id'], $excluded_document_types_ids)) {
                    $result[$id] = [
                        'forbidden_document_type' => "This type of document cannot be uploaded this way."
                    ];
                    continue;
                }

            }
        }

        return $result;
    }

    protected static function policyIsValid($self): array {
        return [];
    }

}
