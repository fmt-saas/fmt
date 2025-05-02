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
        'uuid' =>  [
            'description'   => 'Unique identifier of the document (UUID).',
            'type'          => 'string',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE'],
    'providers'     => ['context', 'orm', 'auth']
]);

['context' => $context, 'orm' => $om, 'auth' => $auth] = $providers;

$user_id = $auth->userId();

// follow a specific logic for access control
$auth->su();

// search for documents matching given hash code (should be only one match)
$collection = Document::search(['uuid', '=', $params['uuid']])->read(['condo_id']);

$document = $collection->last();

if(!$document) {
    throw new Exception("document_unknown", QN_ERROR_UNKNOWN_OBJECT);
}

$condominium = Condominium::id($document['condo_id'])->read(['managing_agent_id'])->first();

if(!$condominium) {
    throw new Exception("condominium_unknown", QN_ERROR_UNKNOWN_OBJECT);
}

/*

on doit garder la synchro entre l'instance Global et Edms
l'instance Edms doit se synchroniser avec l'instance Global pour les Condominium
if($user_id !== $condominium['managing_agent_id']) {
    throw new Exception("access_refused", QN_ERROR_UNKNOWN_OBJECT);
}
*/

$document = $collection->read(['name', 'data', 'content_type'])
    ->adapt('json')
    ->last(true);

$context->httpResponse()
        ->body($document)
        ->send();
