<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace documents;

use equal\orm\Model;

class DocumentSignature extends Model {

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'description'       => "The condominium the document belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'relation'          => ['document_id' => 'condo_id'],
                'store'             => true,
                'instant'           => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The condominium the document belongs to.",
                'function'          => 'calcName',
                'store'             => true
            ],

            'document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'required'          => true,
                'description'       => 'Reference version of the document (immutable).',
                'dependents'        => ['document_hash_sha256', 'condo_id']
            ],

            'hash_sha256' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain:64',
                'relation'          => ['document_id' => 'hash_sha256'],
                'description'       => 'SHA256 hash of the original document.',
                'help'              => 'This field holds the hexadecimal value of the hash and might require a conversion to base64 for exchanges.',
                'store'             => true,
                'readonly'          => true
            ],

            'signature_method' => [
                'type'              => 'string',
                'selection'         => ['ses', 'aes', 'qes'],
                'required'          => true,
                'description'       => 'eIDAS signature level (ses = drawn).'
            ],

            'has_certificate' => [
                'type'              => 'boolean',
                'computed'          => true,
                'description'       => 'True if a certificate is attached.',
                'visible'           => ['signature_method', 'in', ['aes', 'qes']]
            ],

            'sig_drawn' => [
                'type'              => 'binary',
                'usage'             => 'image/png.signature',
                'description'       => 'Handwritten signature (PNG), if present.',
                'visible'           => ['signature_method', '=', 'ses'],
            ],

            'sig_cert' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => 'X.509 certificate as PEM or JSON extract.',
                'visible'           => ['signature_method', 'in', ['aes', 'qes']]
            ],

            'sig_hash' => [
                'type'              => 'string',
                'usage'             => 'text/plain:144',
                'description'       => 'Cryptographic signature (signed hash).',
                'help'              => 'Hexadecimal value of the cryptographic signature is up to 144 chars to be compliant with ECDSA DER/ASN.1',
                'visible'           => ['signature_method', 'in', ['aes', 'qes']],
            ],

            'sig_algo' => [
                'type'              => 'string',
                'description'       => 'Signature algorithm, e.g., RS256, ES256',
                'visible'           => ['signature_method', 'in', ['aes', 'qes']]
            ],

            'sig_timestamp' => [
                'type'              => 'datetime',
                'description'       => 'Timestamp of the signature',
                'default'           => function () { return time(); }
            ],

            'signer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'required'          => true,
                'description'       => 'Person or representative who signed the document.',
                'help'              => 'In case of a AES/QES signature, the identity is used to check the sig_cert against the Signer Identity public key.'
            ]

        ];
    }

    protected static function calcName($self) {
        $result = [];
        $self->read(['signer_identity_id' => ['name'], 'document_id' => ['name'], 'sig_timestamp']);
        foreach($self as $id => $documentSignature) {
            if(!isset($documentSignature['signer_identity_id'], $documentSignature['document_id'])) {
                continue;
            }
            $result[$id] = "{$documentSignature['signer_identity_id']['name']} - {$documentSignature['document_id']['name']} - {$documentSignature['sig_timestamp']}";
        }
        return $result;
    }

}
