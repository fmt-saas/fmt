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
                'description'       => 'Unique identifier from the Master instance.'
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
                'help'              => 'This User is intended to be set on Global instance only and is expected to be created automatically at instance creation.'
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => "The Managing agent the Instance relates to.",
            ],

        ];
    }

    /**
     * This is a "private class": upon creation, assign a unique UUID if on GLOBAL instance
     */
    protected static function oncreate($self, $orm) {
        $self->read(['name', 'instance_type']);
        foreach($self as $id => $instance) {
            if(constant('FMT_INSTANCE_TYPE') === 'global' && $instance['instance_type'] === 'agency') {
                $values = [];
                // generate a new UUID
                do {
                    $uuid = DataGenerator::uuid();
                    $existing = $orm->search(static::class, ['uuid', '=', $uuid]);
                } while( $existing > 0 && count($existing) > 0 );

                $values['uuid'] = $uuid;

                // create a new user for the instance and assign id as user_id
                $domain = parse_url(constant('BACKEND_URL'), PHP_URL_HOST);
                $login = $instance['name'] . '@' . $domain;
                $user = User::create([
                        'login'         => $login,
                        'allow_auth'    => false,
                        'validated'     => true
                    ])
                    ->first();

                $values['user_id'] = $user['id'];
                self::id($id)->update($values);
            }
        }
    }
}
