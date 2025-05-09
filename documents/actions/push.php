<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use documents\Document;
use realestate\property\Condominium;

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given hash.',
    'params'        => [
        'condo_id' => [
            'type'              => 'many2one',
            'description'       => "The condominium the property lot belongs to.",
            'foreign_object'    => 'realestate\property\Condominium',
            'required'          => true
        ],
        'data' => [
            'type'              => 'binary',
            'required'          => true
        ],
        'name' => [
            'type'              => 'string',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

['orm' => $orm] = $providers;


// #todo - check header to retrieve JWT and confirm access


// create Document

$document = Document::create([
        'name'      => $params['name'],
        'data'      => $params['data'],
        'condo_id'  => $params['condo_id']
    ])
    ->first();

// generate unique UUID
do {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits "time_hi_and_version" (4 first bits with version number "4")
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits: 8 bits "clock_seq_hi_and_reserved"; 8 bits "clock_seq_low"
            mt_rand(0, 0x3fff) | 0x8000,
            // 64 bits "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    $existing = $orm->search(Document::getType(), ['uuid', '=', $uuid]);
} while( $existing > 0 && count($existing) > 0 );


$result = Document::id($document['id'])
    ->update(['uuid' => $uuid])
    ->read([
        'uuid',
        'content_type',
        'content_size'
    ])
    ->adapt('json')
    ->first(true);


$context->httpResponse()
        ->status(201)
        ->body($result)
        ->send();
