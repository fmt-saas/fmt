<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace communication\email;

use equal\orm\Model;


class Mailbox extends Model {


    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['email'],
                'store'             => true
            ],

            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'login' => [
                'type'              => 'string',
                'visible'           => ['auth_type', '=', 'basic']
            ],

            'password' => [
                'type'              => 'string',
                'visible'           => ['auth_type', '=', 'basic']
            ],

            'imap_server' => [
                'type'              => 'string',
                'description'       => 'IMAP server hostname.'
            ],

            'imap_port' => [
                'type'              => 'integer',
                'default'           => 993,
                'description'       => 'IMAP server port.'
            ],

            'auth_type' => [
                'type'              => 'string',
                'selection'         => ['basic', 'oauth'],
                'default'           => 'basic',
                'description'       => 'Authentication type.'
            ],

            'access_token' => [
                'type'              => 'string',
                'description'       => 'OAuth2 access token (if applicable).',
                'visible'           => ['auth_type', '=', 'oauth']
            ],

            'refresh_token' => [
                'type'              => 'string',
                'description'       => 'OAuth2 refresh token (if applicable).',
                'visible'           => ['auth_type', '=', 'oauth']
            ],

            'access_token_expiry' => [
                'type'              => 'datetime',
                'description'       => 'Token expiration date/time.',
                'visible'           => ['auth_type', '=', 'oauth']
            ],

            'refresh_token_expiry' => [
                'type'              => 'datetime',
                'description'       => 'Token expiration date/time.',
                'visible'           => ['auth_type', '=', 'oauth']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'ready'
                ],
                'default'           => 'pending',
                'description'       => 'Status of the mailbox.'
            ]

        ];
    }

}
