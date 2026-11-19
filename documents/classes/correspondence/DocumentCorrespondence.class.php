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

            'owner_id' => [
                'type'              => 'many2one',
                'description'       => "The owner concerned by the invitation.",
                'help'              => 'Correspondences are generated for each Owner of each Ownership.',
                'foreign_object'    => 'realestate\ownership\Owner',
                'dependents'        => ['recipient_identity_id']
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership concerned by the invitation.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true,
                'dependents'        => ['recipient_identity_id']
            ],

            'recipient_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity that is the effective recipient of the correspondence.',
                'function'          => 'calcRecipientIdentityId',
                'store'             => false,
                'readonly'          => true
            ],

            'address_block' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/json',
                'description'       => 'JSON object containing the resolved correspondence address block.',
                'function'          => 'calcAddressBlock',
                'store'             => false,
                'readonly'          => true
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
                'usage'             => 'text/plain:35',
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

    protected static function calcRecipientIdentityId($self) {
        $result = [];

        $self->read([
            'owner_id' => ['identity_id'],
            'ownership_id' => [
                'has_external_representative',
                'representative_identity_id',
                'representative_owner_id' => ['identity_id']
            ]
        ]);

        foreach($self as $id => $documentCorrespondence) {
            $result[$id] = null;

            if($documentCorrespondence['owner_id']['identity_id'] ?? null) {
                $result[$id] = $documentCorrespondence['owner_id']['identity_id'];
                continue;
            }

            $ownership = $documentCorrespondence['ownership_id'];
            if(!$ownership) {
                continue;
            }

            if($ownership['has_external_representative'] && $ownership['representative_identity_id']) {
                $result[$id] = $ownership['representative_identity_id'];
                continue;
            }

            if($ownership['representative_owner_id']['identity_id'] ?? null) {
                $result[$id] = $ownership['representative_owner_id']['identity_id'];
            }
        }

        return $result;
    }

    protected static function calcAddressBlock($self) {
        $result = [];

        $self->read([
            'owner_id' => ['identity_id'],
            'recipient_identity_id' => [
                'name',
                'firstname',
                'lastname',
                'title',
                'email',
                'address_street',
                'address_dispatch',
                'address_zip',
                'address_city',
                'address_country'
            ],
            'ownership_id' => [
                'address_recipient',
                'has_external_representative',
                'representative_identity_id',
                'representative_owner_id' => [
                    'identity_id' => ['vat_number']
                ]
            ]
        ]);

        foreach($self as $id => $documentCorrespondence) {
            $result[$id] = '{}';

            $recipientIdentity = $documentCorrespondence['recipient_identity_id'] ?? null;
            $ownership = $documentCorrespondence['ownership_id'] ?? null;
            if(!$recipientIdentity || !$ownership) {
                continue;
            }

            $owner_identity_id = $documentCorrespondence['owner_id']['identity_id'] ?? null;
            $recipient_identity_id = $recipientIdentity['id'];
            $representative_identity_id = $ownership['representative_identity_id'] ?? null;

            $care_of = '';
            $is_direct_owner = $owner_identity_id
                && (int) $recipient_identity_id === (int) $owner_identity_id;

            if(
                !$is_direct_owner
                && $ownership['has_external_representative']
                && (int) $recipient_identity_id === (int) $representative_identity_id
            ) {
                $care_of = $recipientIdentity['name'] ?? '';
            }

            $ownerIdentity = $ownership['representative_owner_id']['identity_id'] ?? null;
            $vat_number = $ownerIdentity['vat_number'] ?? '';

            $result[$id] = json_encode([
                'name'                => $ownership['address_recipient'] ?: ($recipientIdentity['name'] ?? ''),
                'firstname'           => $recipientIdentity['firstname'] ?? '',
                'lastname'            => $recipientIdentity['lastname'] ?? '',
                'title'               => $recipientIdentity['title'] ?? '',
                'email'               => $recipientIdentity['email'] ?? '',
                'care_of'             => $care_of,
                'address_street'      => $recipientIdentity['address_street'] ?? '',
                'address_dispatch'    => $recipientIdentity['address_dispatch'] ?? '',
                'address_zip'         => $recipientIdentity['address_zip'] ?? '',
                'address_city'        => $recipientIdentity['address_city'] ?? '',
                'address_country'     => $recipientIdentity['address_country'] ?? '',
                'has_vat'             => $vat_number !== '',
                'vat_number'          => $vat_number,
                'owner_vat_number'    => $vat_number
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        return $result;
    }

    protected static function onupdateDocumentId($self) {
        $self->read(['document_id']);
        foreach($self as $id => $assemblyInvitation) {
            self::id($id)->update(['has_document' => (bool) $assemblyInvitation['document_id']]);
        }
    }

    protected static function canupdate($self, $values) {
        $self->read(['is_sent']);
        $allowed_fields = ['document_id', 'is_acknowledged', 'is_sent', 'sent_date', 'download_link'];
        foreach($self as $id => $documentCorrespondence) {
            if($documentCorrespondence['is_sent']) {
                if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                    return ['is_sent' => ['non_editable' => 'Invite cannot be changed once sent.']];
                }
            }
        }
        return [];
    }
}
