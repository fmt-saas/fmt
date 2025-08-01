<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
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

// retrieve user id from JWT (should be a managing_agent_id)
$user_id = $auth->userId();

// follow a specific logic for access control
$auth->su();

// search for documents matching given hash code (should be only one match)
$collection = Document::search(['uuid', '=', $params['uuid']])->read(['condo_id']);

$document = $collection->last();

if(!$document) {
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}


/*
// #todo
on doit garder la synchro entre l'instance Global et Edms
l'instance Edms doit se synchroniser avec l'instance Global pour les Condominium


$condominium = Condominium::id($document['condo_id'])
    ->read(['managing_agent_id'])
    ->first();

if(!$condominium) {
    throw new Exception("condominium_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

if($user_id !== $condominium['managing_agent_id']) {
    throw new Exception("access_refused", EQ_ERROR_UNKNOWN_OBJECT);
}
*/

$document = $collection->read(['name', 'data', 'content_type'])
    ->adapt('json')
    ->last(true);

$context->httpResponse()
        ->body($document)
        ->send();
