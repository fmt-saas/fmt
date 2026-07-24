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
                'selection'         => [
                    'all',
                    'general_assembly_minutes',
                    'expense_statement',
                    'balance_sheet'
                ],
                'default'           => 'all'
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'visible'           => ['all', '=', 'object.document_filter'],
                'domain'            => [
                    [ ['condo_id', '=', 'object.condo_id'] ]
                ]
            ],

            'general_assembly_minutes_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'visible'           => ['general_assembly_minutes', '=', 'object.document_filter'],
                'domain'            => [
                    [ ['condo_id', '=', 'object.condo_id'], ['document_type_code', '=', 'general_assembly_document'], ['document_subtype_code', '=', 'minutes'] ]
                ],
                'onupdate'          => 'onupdateGeneralAssemblyMinutesDocumentId'
            ],

            'expense_statement_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'visible'           => ['expense_statement', '=', 'object.document_filter'],
                'domain'            => [
                    [ ['condo_id', '=', 'object.condo_id'], ['document_type_code', '=', 'expense_statement'] ]
                ],
                'onupdate'          => 'onupdateExpenseStatementDocumentId'
            ],

            'balance_sheet_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'visible'           => ['balance_sheet', '=', 'object.document_filter'],
                'domain'            => [
                    [ ['condo_id', '=', 'object.condo_id'], ['document_type_code', '=', 'balance_sheet'] ]
                ],
                'onupdate'          => 'onupdateBalanceSheetDocumentId'
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

    protected static function onupdateGeneralAssemblyMinutesDocumentId($self) {
        $self->read(['general_assembly_minutes_document_id']);
        foreach($self as $id => $ownershipTransferAttachment) {
            self::id($id)->update(['document_id' => $ownershipTransferAttachment['general_assembly_minutes_document_id']]);
        }
    }

    protected static function onupdateExpenseStatementDocumentId($self) {
        $self->read(['expense_statement_document_id']);
        foreach($self as $id => $ownershipTransferAttachment) {
            self::id($id)->update(['document_id' => $ownershipTransferAttachment['expense_statement_document_id']]);
        }
    }

    protected static function onupdateBalanceSheetDocumentId($self) {
        $self->read(['balance_sheet_document_id']);
        foreach($self as $id => $ownershipTransferAttachment) {
            self::id($id)->update(['document_id' => $ownershipTransferAttachment['balance_sheet_document_id']]);
        }
    }

}