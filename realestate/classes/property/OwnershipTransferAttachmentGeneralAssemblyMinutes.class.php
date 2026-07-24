<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferAttachmentGeneralAssemblyMinutes extends OwnershipTransferAttachment {

    public static function getColumns() {
        return [

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'visible'           => ['all', '=', 'object.document_filter'],
                'domain'            => [
                    [ ['condo_id', '=', 'object.condo_id'], ['document_type_code', '=', 'general_assembly_document'], ['document_subtype_code', '=', 'minutes'] ]
                ]
            ],

            'attachment_section' => [
                'type'              => 'string',
                'description'       => 'Pseudo type (type+subtype) of target document.',
                'default'           => 'general_assembly_minutes'
            ]

        ];
    }

}