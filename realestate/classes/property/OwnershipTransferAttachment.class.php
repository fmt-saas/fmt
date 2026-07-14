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

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'EDMS document attached to the ownership transfer.',
                'domain'            => [
                    ['condo_id', '=', 'object.condo_id']
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