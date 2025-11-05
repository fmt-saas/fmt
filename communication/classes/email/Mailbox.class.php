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
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the email relates to.",
                'foreign_object'    => 'realestate\property\Condominium'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['subject']
            ],


            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'required'          => true
            ],

            'login' => [
                'type'              => 'string',
            ],

            'password' => [
                'type'              => 'string',
            ],

            'imap_server' => [
                'type'              => 'string',
                'default'           => 'imap.office365.com',
                'description'       => 'IMAP server hostname.'
            ],

            'imap_port' => [
                'type'              => 'integer',
                'default'           => 993,
                'description'       => 'IMAP server port.'
            ],

            'auth_type' => [
                'type'              => 'string',
                'selection'         => ['basic', 'oauth2'],
                'default'           => 'basic',
                'description'       => 'Authentication type.'
            ],

            'access_token' => [
                'type'              => 'string',
                'description'       => 'OAuth2 access token (if applicable).'
            ],

            'refresh_token' => [
                'type'              => 'string',
                'description'       => 'OAuth2 refresh token (if applicable).'
            ],

            'token_expiry' => [
                'type'              => 'datetime',
                'description'       => 'Token expiration date/time.'
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
