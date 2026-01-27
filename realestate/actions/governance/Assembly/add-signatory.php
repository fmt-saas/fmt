<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use documents\DocumentSignature;
use realestate\governance\Assembly;
use realestate\ownership\Owner;
use identity\Identity;
use realestate\governance\AssemblyAttendee;

[$params, $providers] = eQual::announce([
    'description'   => "Add a signatory (attendee) to the Assembly Minutes of the given assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ],

        'attendee_id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly attendee that is signatory.",
            'foreign_object'    => 'realestate\governance\AssemblyAttendee',
            'domain'            => [
                'has_left', '=', false
            ],
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
    ->read(['status', 'condo_id', 'minutes_document_id'])
    ->first(true);

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

$assemblyAttendee = AssemblyAttendee::id($params['attendee_id'])
    ->read(['identity_id'])
    ->first();

if(!$assemblyAttendee) {
    throw new Exception("unknown_attendee", EQ_ERROR_INVALID_PARAM);
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


// 2) create document signature

$values = [
    'document_id'           => $assembly['minutes_document_id'],
    'signer_identity_id'    => $assemblyAttendee['identity_id'],
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

// 3) update attendee

$attendee = AssemblyAttendee::id($params['attendee_id'])
    ->update([
        'minutes_document_signature_id' => $documentSignature['id'],
        'has_signed_minutes'            => true
    ])
    ->adapt('json')
    ->first(true);


$context->httpResponse()
        ->body($attendee)
        ->send();
