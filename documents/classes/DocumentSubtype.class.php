<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents;

use equal\data\DataGenerator;
use equal\orm\Model;

class DocumentSubtype extends Model {

    public static function constants() {
        return ['FMT_INSTANCE_TYPE'];
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the document Subtype.',
                'required'          => true
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                'unique'            => true,
                'description'       => 'Unique supplier identifier provided by GLOBAL instance.'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Unique code identifier of the document Subtype.',
                'required'          => true,
                'unique'            => true
            ],

            'folder_code' => [
                'type'              => 'string',
                'description'       => 'Code of the Folder node a document by this type must be assigned to.'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain.short',
                'description'       => 'Description of the purpose and usage of the tag.'
            ],

            'document_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentType',
                'description'       => 'Parent documents type.'
            ],

            'document_type_uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                'description'       => 'Unique document type identifier provided by GLOBAL instance.'
            ],

            'recording_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\recording\RecordingRule',
                'foreign_field'     => 'document_subtype_id',
                'description'       => 'Rules matching the document subtype.'
            ],

            'labeling_rules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\labeling\LabelingRule',
                'foreign_field'     => 'document_subtype_id',
                'description'       => 'Rules matching the document subtype.'
            ],

            'documents_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'documents\Document',
                'foreign_field'     => 'document_subtype_id',
                'description'       => 'Documents matching the document subtype.'
            ]

        ];
    }

    /**
     * This is a "private class": upon creation, assign a unique UUID if on GLOBAL instance
     */
    protected static function oncreate($self, $orm) {
        foreach($self as $id => $object) {
            if(constant('FMT_INSTANCE_TYPE') === 'global') {
                do {
                    $uuid = DataGenerator::uuid();
                    $existing = $orm->search(static::class, ['uuid', '=', $uuid]);
                } while( $existing > 0 && count($existing) > 0 );

                self::id($id)->update(['uuid' => $uuid]);
            }
        }
    }

    protected static function doSyncUuidLinks($self) {
        $self->read(['document_type_id', 'document_type_uuid']);
        foreach($self as $id => $document_subtype) {
            if(!empty($document_subtype['document_type_uuid'])) {
                $document = DocumentType::search(['uuid', '=', $document_subtype['document_type_uuid']])
                    ->first();

                if($document_subtype['document_type_id'] !== $document['id']) {
                    self::id($id)->update(['document_type_id' => $document['id']]);
                }
            }
        }
    }

    public static function getActions() {
        return [
            'sync_uuid_links' => [
                'description'   => 'Synchronize the uuid links.',
                'policies'      => [],
                'function'      => 'doSyncUuidLinks'
            ]
        ];
    }
}
