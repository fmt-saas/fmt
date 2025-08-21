<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\DocumentSignature;
use realestate\governance\Assembly;
use realestate\ownership\Owner;
use identity\Identity;
use realestate\governance\AssemblyAttendee;

[$params, $providers] = eQual::announce([
    'description'   => "Checks if all owners have been invited to the target assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'sig_drawn' => [
            'type'              => 'binary',
            'usage'             => 'image/png.signature',
            'description'       => 'Handwritten signature (PNG), if present.',
            'visible'           => ['sig_method', '=', 'ses'],
        ],

        'sig_cert' => [
            'type'              => 'binary',
            // #todo - not supported yet by UsageFactory
            /*'usage'             => 'application/pkix-cert',*/
            'description'       => 'X.509 certificate of the signer, as DER-encoded base64 value.',
            'visible'           => ['sig_method', 'in', ['aes', 'qes']]
        ],

        'sig_hash' => [
            'type'              => 'string',
            'usage'             => 'text/plain:1000',
            'description'       => 'Cryptographic signature (signed hash).',
            'help'              => 'Base64 value of the cryptographic signature (RSA or ECDSA).',
            'visible'           => ['sig_method', 'in', ['aes', 'qes']],
        ],

        'sig_algo_oid' => [
            'type'              => 'string',
            'description'       => 'Signature algorithm, e.g., RSA, ECC',
            'visible'           => ['sig_method', 'in', ['aes', 'qes']]
        ],

        'sig_method' => [
            'type'              => 'string',
            'selection'         => ['ses', 'qes'],
            'required'          => true,
            'description'       => 'eIDAS signature level (ses = drawn).'
        ],

        'has_mandate' => [
            'type'              => 'boolean',
            'description'       => "Indicates whether the attendee has a mandate to represent one or more other ownerships.",
            'help'              => "This field simply indicates whether proxies have been presented but does not guarantee their validity.",
            'default'           => false
        ],

        'is_owner' => [
            'type'              => 'boolean',
            'description'       => "Indicates whether the attendee is a property lot owner or not.",
            'help'              => "If an attendee is not an owner, it is assumed that he has at least one mandate.",
            'required'          => true
        ],

        'owner_id' => [
            'type'              => 'many2one',
            'description'       => "The owner concerned by the invitation.",
            'help'              => 'A single invite is generated for each Ownership (representative).',
            'foreign_object'    => 'realestate\ownership\Owner',
            'visible'           => ['is_owner', '=', true]
        ],

        'firstname' => [
            'type'              => 'string',
            'description'       => "Full name of the contact (must be a person, not a role).",
            'visible'           => [['is_owner', '=', true], ['sig_method', '=', 'ses']],
        ],

        'lastname' => [
            'type'              => 'string',
            'description'       => 'Reference contact surname.',
            'visible'           => [['is_owner', '=', true], ['sig_method', '=', 'ses']],
        ]

    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$computeSignerInfoFromCert = function (string $cert): array {
    $pem =  "-----BEGIN CERTIFICATE-----\n"
        . chunk_split(base64_encode($cert), 64, "\n")
        . "-----END CERTIFICATE-----\n";

    $parsed = openssl_x509_parse($pem, false);

    if(!$parsed || !isset($parsed['subject'])) {
        throw new Exception('invalid_X509_cert', EQ_ERROR_UNKNOWN);
    }

    $subject = $parsed['subject'] ?? [];

    $firstname_words = preg_split('/\s+/', $subject['givenName']);
    $lastname_words = preg_split('/\s+/', $subject['surname']);

    $common_name_words = preg_split('/\s+/', preg_replace('/\s*\(.*\)$/u', '', $subject['commonName']));

    $intersectWords = function (array $a, array $b) {
        $aLower = array_map('mb_strtolower', $a);
        $bLower = array_map('mb_strtolower', $b);
        $inter = array_uintersect_assoc($a, $a, function ($w1, $w2) use ($aLower, $bLower) {
            return (in_array(mb_strtolower($w1), $bLower)) ? 0 : 1;
        });
        return $inter;
    };

    $res = [
        'firstname'                 => implode(' ', $intersectWords($common_name_words, $firstname_words)),
        'lastname'                  => implode(' ', $intersectWords($common_name_words, $lastname_words)),
        'citizen_identification'    => $subject['serialNumber'] ?? '',
    ];

    return $res;
};


// 1) check parameters consistency

$assembly = Assembly::id($params['id'])
    ->read(['status', 'condo_id', 'attendance_register_document_id'])
    ->first(true);

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

if($params['sig_method'] === 'qes') {

    if(empty($params['sig_cert'])) {
        throw new Exception("missing_signature_certificate", EQ_ERROR_MISSING_PARAM);
    }

    if(empty($params['sig_hash'])) {
        throw new Exception("missing_signature_hash", EQ_ERROR_MISSING_PARAM);
    }

    if(empty($params['sig_algo_oid'])) {
        throw new Exception("missing_signature_algorithm", EQ_ERROR_MISSING_PARAM);
    }

}
else {
    if(empty($params['sig_drawn'])) {
        throw new Exception("missing_signature_image", EQ_ERROR_MISSING_PARAM);
    }
}

if($params['is_owner']) {
    if(!$params['owner_id']) {
        throw new Exception("missing_owner_id", EQ_ERROR_MISSING_PARAM);
    }
}
else {
    if($params['sig_method'] === 'ses') {
        if(empty($params['firstname'])) {
            throw new Exception("missing_firstname", EQ_ERROR_INVALID_PARAM);
        }
        if(empty($params['lastname'])) {
            throw new Exception("missing_lastname", EQ_ERROR_INVALID_PARAM);
        }

    }
}

// 2) identity retrieval

$identity_id = 0;

if($params['is_owner']) {
    $owner = Owner::id($params['owner_id'])
        ->read(['id', 'name', 'identity_id'])
        ->first(true);

    $identity_id = $owner['identity_id'];
}
else {
    if($params['sig_method'] === 'ses') {
        // external without citizen ID (only firstname, lastname & drawn signature)
        // create a new identity
        // #memo - we have no way to avoid duplicates here
        $identity = Identity::create([
                'type_id'       => 1,
                'firstname'     => $params['firstname'],
                'lastname'      => $params['lastname']
            ])
            ->first();

        $identity_id = $identity['id'];
    }
    // retrieve info from the certificate
    else {

        $infos = $computeSignerInfoFromCert($params['sig_cert']);

        // #memo - for GDPR compliance, we do not store the citizen identification number
        // #memo - pseudonymization is acceptable & proportional to the finality (validate link between signature and identity)
        $hash  = hash('sha256', $infos['citizen_identification'] . constant('AUTH_SECRET_KEY'));

        $identity = Identity::search(['hash_sha256', '=', $hash])->first();

        if($identity) {
            $identity_id = $identity['id'];
        }
        else {
            // create a new identity
            $identity = Identity::create([
                    'type_id'       => 1,
                    'firstname'     => $infos['firstname'],
                    'lastname'      => $infos['lastname'],
                    // #memo - manually set the computed hash, since we cannot store the citizen identification number
                    'hash_sha256'   => $hash
                ])
                ->first();

            $identity_id = $identity['id'];
        }
    }
}

if(!$identity_id) {
    throw new Exception("failed_linking_identity", EQ_ERROR_UNKNOWN);
}

// make sure an attendee targeting the same identity is not already registered for the assembly
$existingAttendee = AssemblyAttendee::search([
        ['assembly_id', '=', $params['id']],
        ['identity_id', '=', $identity_id]
    ])
    ->first();

if($existingAttendee) {
    throw new Exception("attendee_already_registered", EQ_ERROR_INVALID_PARAM);
}

// 3) create document signature

$values = [
    'document_id'           => $assembly['attendance_register_document_id'],
    'signer_identity_id'    => $identity_id,
    'sig_method'            => $params['sig_method'],
    'sig_timestamp'         => time()
];

if($params['sig_method'] === 'ses') {
    $values['sig_drawn'] = $params['sig_drawn'];
}
else {
    $values['sig_cert'] = $params['sig_cert'];
    $values['sig_hash'] = $params['sig_hash'];
    $values['sig_algo_oid'] = $params['sig_algo_oid'];
}

$documentSignature = DocumentSignature::create($values)->first();

// 4) create attendee

$attendee = AssemblyAttendee::create([
        'condo_id'              => $assembly['condo_id'],
        'assembly_id'           => $params['id'],
        'identity_id'           => $identity_id,
        'has_mandate'           => $params['has_mandate'],
        'document_signature_id' => $documentSignature['id'],
        'has_signed'            => true
    ])
    ->adapt('json')
    ->first(true);


$context->httpResponse()
        ->body($attendee)
        ->send();
