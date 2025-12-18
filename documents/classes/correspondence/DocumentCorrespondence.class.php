<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents\correspondence;

class DocumentCorrespondence extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ],

            'owner_id' => [
                'type'              => 'many2one',
                'description'       => "The owner concerned by the invitation.",
                'help'              => 'A single invite is generated for each Ownership (representative).',
                'foreign_object'    => 'realestate\ownership\Owner',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership concerned by the invitation.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
            ],

            'document_id' => [
                'type'              => 'many2one',
                'description'       => 'The document (PDF) of the invitation, if any.',
                'foreign_object'    => 'documents\Document',
                'onupdate'          => 'onupdateDocumentId',
                'visible'           => [['has_document', '=', true], ['communication_method', '<>', 'email']]
            ],

            'has_document' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if the document of the invitation has been generated.',
                'default'           => false
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'visible'           => ['communication_method', '=', 'email']
            ],

            'sent_date' => [
                'type'              => 'datetime',
                'description'       => "Date at which the original (first) invite was sent.",
                'help'              => 'This date is immutable (@see `canupdate`). The original date must remain the same in case of multiple generation.'
            ],

            'communication_method' => [
                'type'              => 'string',
                'selection'         => [
                    'email',
                    'postal',
                    'postal_registered',
                    'postal_registered_receipt'
                ],
                'description'       => "Method used to send the invitation.",
                'default'           => 'postal_registered'
            ],

            'is_sent' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the invitation has been sent.",
                'default'           => false
            ],

            'is_acknowledged' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the invitation has been acknowledged by the owner.",
                'default'           => false
            ]

        ];
    }

    protected static function onupdateDocumentId($self) {
        $self->read(['document_id']);
        foreach($self as $id => $assemblyInvitation) {
            self::id($id)->update(['has_document' => (bool) $assemblyInvitation['document_id']]);
        }
    }

    protected static function canupdate($self, $values) {
        $self->read(['is_sent']);
        $allowed = ['document_id', 'is_acknowledged'];
        foreach($self as $id => $assemblyInvitation) {
            if($assemblyInvitation['is_sent']) {
                if(count(array_diff(array_keys($values), $allowed)) > 0) {
                    return ['is_sent' => ['non_editable' => 'Invite cannot be changed once sent.']];
                }
            }
        }
        return [];
    }
}
