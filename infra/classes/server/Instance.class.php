<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\server;

use identity\User;
use equal\data\DataGenerator;
use equal\orm\Model;

class Instance extends Model {

    public static function constants() {
        return ['FMT_INSTANCE_TYPE', 'BACKEND_URL'];
    }

    public static function getDescription() {
        return 'Instance manages service or product instances, detailing type, version, URL, access information, and running software.';
    }

    public static function getColumns() {

        return [

            'name'    => [
                'type'              => 'string',
                'unique'            => true,
                'required'          => true,
                'description'       => 'Unique identifier of the instance.'
            ],

            'uuid' => [
                'type'              => 'string',
                'usage'             => 'text/plain:36',
                // #memo - commented for testing because items are on the same instance
                // #todo - uncomment for PROD
                // 'unique'            => true,
                'description'       => 'Unique identifier from the Master instance.',
                'visible'           => ['instance_type', '=', 'agency']
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short description of the instance.'
            ],

            'last_synced' => [
                'type'              => 'datetime',
                'description'       => 'Date of last automatic status update.',
                'help'              => 'The "up" field can be auto updated by the action "infra_server_Instance_fetch-status".'
            ],

            'up' => [
                'type'              => 'boolean',
                'description'       => 'Is the instance currently up, is set according to the last infra\server\Status retrieval.',
                'default'           => false
            ],

            'instance_type' => [
                'type'              => 'string',
                'selection'         => [
                    'global',
                    'agency'
                ],
                'description'       => 'Type of instance.',
                'default'           => 'agency'
            ],

            'url' => [
                'type'              => 'string',
                'usage'             => 'uri/url',
                'description'       => 'Front-end home URL.'
            ],

            'server_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\server\Server',
                'description'       => 'Server (host) on which the instance runs.',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\User',
                'description'       => 'User for API requests from the instance to Global instance.',
                'help'              => "Created automatically at instance creation, allows access from the foreign instance to this instance's API.",
                'visible'           => ['instance_type', '<>', \eQual::constant('FMT_INSTANCE_TYPE')]
            ],

            'access_token' => [
                'type'              => 'string',
                'usage'             => 'text/plain.small',
                'description'       => 'Token to use to access the instance API.',
                'visible'           => ['instance_type', '<>', \eQual::constant('FMT_INSTANCE_TYPE')]
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => 'The Managing agent the Instance relates to.',
                'visible'           => ['instance_type', '=', 'agency']
            ],

            'user_token_generated' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Has the user access token been generated yet.',
                'store'             => false,
                'function'          => 'calcUserTokenGenerated'
            ],

            'has_dns_record' => [
                'type'              => 'boolean',
                'description'       => 'Marks the instance as having or not DNS record.',
                'default'           => false
            ],

            'refreshed' => [
                'type'              => 'datetime',
                'description'       => 'The last time the status of the instance was refreshed.',
                'help'              => 'Updated when the information on branches, config, tasks and required data are refreshed (refresh-self-status).'
            ],

            'refreshed_logs' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => 'Human readable descriptor of the refreshed information about the instance.'
            ],

            'branch_equal' => [
                'type'              => 'string',
                'description'       => 'The current name of the eQual git branch.'
            ],

            'branch_fmt' => [
                'type'              => 'string',
                'description'       => 'The current name of the FMT git branch.'
            ],

            'statuses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'infra\server\InstanceStatus',
                'foreign_field'     => 'instance_id',
                'description'       => 'Statuses of the instance.'
            ],

            'checks_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'infra\server\InstanceCheck',
                'foreign_field'     => 'instance_id',
                'description'       => 'Checks of the instance.'
            ]

        ];
    }

    protected static function policyCanCreateUser($self) {
        $result = [];
        $self->read(['instance_type', 'user_id']);
        foreach($self as $id => $instance) {
            if($instance['instance_type'] === constant('FMT_INSTANCE_TYPE')) {
                $result[$id] = [
                    'not_needed' => "The instance doesn't need access to other instances of the same type."
                ];
            }
            elseif($instance['user_id']) {
                $result[$id] = [
                    'existing_user' => "The instance's user already exists."
                ];
            }
        }

        return $result;
    }

    public static function getPolicies() {
        return [
            'can_create_user' => [
                'description' => "Verifies that the instance user is not already created.",
                'function'    => 'policyCanCreateUser'
            ]
        ];
    }

    protected static function doCreateUser($self, $auth) {
        $self->read(['name', 'instance_type']);
        $user_id = $auth->userId();
        $auth->su();
        foreach($self as $id => $instance) {
            $domain = parse_url(constant('BACKEND_URL'), PHP_URL_HOST);
            $login = $instance['name'] . '@' . $domain;
            $user = User::create([
                    'login'         => $login,
                    'allow_auth'    => false,
                    'validated'     => true,
                    'is_system'     => true
                ])
                ->first();

            self::id($id)->update(['user_id' => $user['id']]);
        }
        $auth->su($user_id);
    }

    public static function getActions() {
        return [
            'create_user' => [
                'description'   => 'Create the agency instance user.',
                'policies'      => ['can_create_user'],
                'function'      => 'doCreateUser'
            ]
        ];
    }

    /**
     * This is a "private class": upon creation, assign a unique UUID if on GLOBAL instance
     */
    protected static function oncreate($self, $orm) {
        $self->read(['instance_type']);
        foreach($self as $id => $instance) {
            if(constant('FMT_INSTANCE_TYPE') === 'global' && $instance['instance_type'] === 'agency') {
                // generate a new UUID
                do {
                    $uuid = DataGenerator::uuid();
                    $existing = $orm->search(static::class, ['uuid', '=', $uuid]);
                } while( $existing > 0 && count($existing) > 0 );

                $orm->update(static::class, $id, ['uuid' => $uuid]);
            }
        }
    }

    public static function canupdate($self, $values) {
        if(isset($values['name'])) {
            // validation needed, else user creation can fail
            if(!filter_var($values['name'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                return ['name' => ['invalid_name' => 'Invalid instance name.']];
            }
        }

        return parent::canupdate($self);
    }

    protected static function onafterupdate($self) {
        $self->read(['instance_type', 'user_id']);
        foreach($self as $id => $instance) {
            if($instance['instance_type'] !== constant('FMT_INSTANCE_TYPE') && !$instance['user_id']) {
                self::id($id)->do('create_user');
            }
        }
    }

    protected static function calcUserTokenGenerated($self) {
        $result = [];
        $self->read(['user_id' => ['access_tokens_ids' => ['token_type']]]);
        foreach($self as $id => $instance) {
            $user_token_generated = false;
            foreach($instance['user_id']['access_tokens_ids'] ?? [] as $token) {
                if($token['token_type'] === 'access_token') {
                    $user_token_generated = true;
                }
            }

            $result[$id] = $user_token_generated;
        }

        return $result;
    }
}
