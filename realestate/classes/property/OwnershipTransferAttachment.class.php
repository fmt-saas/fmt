<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\property;

class OwnershipTransferAttachment extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'condo_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\Condominium',
                'description'       => 'Condominium the attachment relates to.',
                'required'          => true
            ],

            'ownership_transfer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\property\OwnershipTransfer',
                'description'       => 'Ownership transfer the attachment relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'document_filter' => [
                'type'              => 'string',
                'description'       => 'Pseudo type (type+subtype) of target document.',
                'selection'            => [
                    'all',
                    'general_assembly_minutes',
                    'expense_statement',
                    'balance_sheet'
                ],
                'required'          => true
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'domain'            => [
                    [ ['condo_id', '=', 'object.condo_id'], ['all', '=', 'object.document_filter'] ],
                    [ ['condo_id', '=', 'object.condo_id'], ['general_assembly_minutes', '=', 'object.document_filter'], ['document_type_code', '=', 'general_assembly_document'], ['document_subtype_code', '=', 'minutes'] ],
                    [ ['condo_id', '=', 'object.condo_id'], ['expense_statement', '=', 'object.document_filter'], ['document_type_code', '=', 'expense_statement'] ],
                    [ ['condo_id', '=', 'object.condo_id'], ['balance_sheet', '=', 'object.document_filter'], ['document_type_code', '=', 'balance_sheet'] ]
                ],
                'required'          => true
            ],

            'attachment_target' => [
                'type'              => 'string',
                'selection'         => [
                    'paragraph_1',
                    'paragraph_2',
                    'paragraph_1_2'
                ],
                'description'       => 'Role of the document in the ownership transfer process.',
                'required'          => true
            ]

        ];
    }
}