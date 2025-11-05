<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use communication\email\Email;
use communication\email\Mailbox;
use documents\Document;

[$params, $providers] = eQual::announce([
    'description'	=>	"Refresh the access token of a given Mailbox.",
    'params' 		=>	[
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'communication\email\Mailbox',
            'description'      => 'Identifier of the Assembly item (resolution).',
            'required'          => true
        ]
    ],
    'constants'     => ['BACKEND_URL'],
    'access'        => [
        'visibility' => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL', 'AUTH_ACCESS_TOKEN_VALIDITY', 'AUTH_TOKEN_HTTPS', 'FMT_INSTANCE_TYPE']
]);


// faire un switch selon le imap_server

/*
            outlook.office365.com -> office
            imap.gmail.com  -> gmail

*/