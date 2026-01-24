<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use documents\Document;
use identity\User;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Return raw data (with original MIME) of a document identified by given identifier.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the document.',
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Document',
            'required'          => true
        ],
        'disposition' => [
            'type'          => 'string',
            'selection'     => [
                'inline',
                'attachment'
            ],
            'default'       => 'inline'
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/octet-stream'
    ],
    'constants'     => ['FMT_INSTANCE_TYPE', 'FMT_API_URL_EDMS'],
    'providers'     => ['context', 'orm', 'auth', 'adapt']
]);

['context' => $context, 'orm' => $om, 'auth' => $auth, 'adapt' => $adapt] = $providers;

$user_id = $auth->userId();

/*
// #memo - logic change, see @sync
// search for documents matching given hash code (should be only one match)
$collection = Document::id($params['id']);
$document = $collection->read(['uuid'])->first();

if(!$document) {
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

if(constant('FMT_INSTANCE_TYPE') === 'edms' && !$document['uuid']) {
    throw new Exception("invalid_document", EQ_ERROR_UNKNOWN_OBJECT);
}

// pull document data from EDMS server
if($document['uuid']) {
    // #todo - inject APP_TOKEN in header
    $url = constant('FMT_API_URL_EDMS');
    $request = new HttpRequest('GET '.$url.'?get=documents_pull&uuid=' . $document['uuid']);
    $response = $request->send();

    $result = $response->body();

    if(!isset($result['data'], $result['name'], $result['content_type'])) {
        throw new Exception('invalid_response', EQ_ERROR_UNKNOWN);
    }

    if(strlen($result['data']) <= 0) {
        throw new Exception('empty_response', EQ_ERROR_UNKNOWN);
    }

    $adapter = $adapt->get('json');

    $content_type = $result['content_type'];
    $filename = $result['name'];
    $output = $adapter->adaptIn($result['data'], 'binary');
}
// no UUID, fallback to data (this can occur when condo_id is still missing)
else {
    $document = $collection->read(['name', 'data', 'content_type'])->first();
    $content_type = $document['content_type'];
    $filename = $document['name'];
    $output = $document['data'];
}
*/


$document = Document::id($params['id'])
    ->read([
        'document_visibility', 'condo_id', 'ownership_id', 'name', 'data', 'content_type'
    ])
    ->first();

$content_type = $document['content_type'];
$filename = $document['name'];
$output = $document['data'];


// #todo

// check visibility rules
switch($document['document_visibility']) {
    case 'public':
        // visible to all condo owners + syndic
        // make sure user relates to condo_id of the document
        $user = User::id($user_id)->read(['identity_id' => ['employee_id']])->first();

        if(!($user['identity_id']['employee_id'] ?? null)) {
            $owners = Owner::search(['identity_id', '=', $user['identity_id']['id']])->read(['condo_id']);
            $found = false;
            foreach($owners as $owner_id => $owner) {
                if($owner['condo_id'] === $document['condo_id']) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
            }
        }
        break;
    case 'protected':
        // visible only to syndic
        // user must be linked to an employee
        $user = User::id($user_id)->read(['employee_id'])->first();

        if(!$user || !($user['employee_id'] ?? null)) {
            throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
        }
        break;
    case 'private':
        // visible only a single owner (to which the document is linked) + syndic
        // make sure the user relates to the ownership_id of the document
        $user = User::id($user_id)->read(['identity_id' => ['employee_id']])->first();

        if(!($user['identity_id']['employee_id'] ?? null)) {
            $owners = Owner::search(['identity_id', '=', $user['identity_id']['id']])->read(['ownership_id']);
            $found = false;
            foreach($owners as $owner_id => $owner) {
                if($owner['ownership_id'] === $document['ownership_id']) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                throw new Exception('protected_document', EQ_ERROR_NOT_ALLOWED);
            }
        }
        break;
}

$context->httpResponse()
        ->header('Content-Disposition', $params['disposition'].'; filename="'.$filename.'"')
        ->header('Content-Type', $content_type)
        ->body($output, true)
        ->send();
