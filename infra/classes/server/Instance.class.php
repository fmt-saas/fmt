<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\server;

use core\User;
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
                'help'              => "Created automatically at instance creation, allows access from the foreign instance to this instance's API."
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => "The Managing agent the Instance relates to.",
                'visible'           => ['instance_type', '=', 'agency']
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

    protected static function doCreateUser($self) {
        $self->read(['name', 'instance_type']);
        foreach($self as $id => $instance) {
            $domain = parse_url(constant('BACKEND_URL'), PHP_URL_HOST);
            $login = $instance['name'] . '@' . $domain;
            $user = User::create([
                'login'         => $login,
                'allow_auth'    => false,
                'validated'     => true
            ])
                ->first();

            self::id($id)->update(['user_id' => $user['id']]);
        }
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
}
