<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\server;

use equal\data\DataGenerator;
use equal\orm\Model;

class Server extends Model {

    public static function constants() {
        return ['FMT_INSTANCE_TYPE'];
    }

    public static function getDescription() {
        return 'A Server hosts one or more instances.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Internal identification ex. trg.be-master.',
                'unique'            => true
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
                'description'       => 'Short description of the Server.',
            ],

            'synced' => [
                'type'              => 'datetime',
                'description'       => 'Date of last automatic status update.',
                'help'              => 'The "up" field can be auto updated by the action "infra_server_Server_fetch-status".'
            ],

            'up' => [
                'type'              => 'boolean',
                'description'       => 'Is the server currently up, is set according to the last infra\server\Status retrieval.',
                'default'           => false,
                'onupdate'          => 'onupdateUp'
            ],

            'b2_api_url' => [
                'type'              => 'string',
                'usage'             => 'uri/url',
                'description'       => 'The b2 API URL of the server.'
            ],

            'b2_api_password' => [
                'type'              => 'string',
                'description'       => 'The b2 API password of the server.',
                'visible'           => false
            ],

            'instances_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'server_id',
                'foreign_object'    => 'infra\server\Instance',
                'ondetach'          => 'delete',
                'description'       => 'Instances running on the server.'
            ],

            'instances_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcInstancesCount'
            ],

            'statuses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'infra\server\Status',
                'foreign_field'     => 'server_id',
                'description'       => 'Statuses of the server.'
            ]

        ];
    }

    protected static function calcInstancesCount($self): array {
        $result = [];
        $self->read(['instances_ids']);
        foreach($self as $id => $server) {
            $result[$id] = count($server['instances_ids']);
        }

        return $result;
    }

    public static function getActions(): array {
        return [
        ];
    }

    /**
     * This is a "private class": upon creation, assign a unique UUID if on GLOBAL instance
     */
    protected static function oncreate($self, $orm) {
        foreach($self as $id => $object) {
            if(constant('FMT_INSTANCE_TYPE') === 'global') {
                do {
                    $uuid = DataGenerator::uuid();
                    $existing = $orm->search(static::class, ['uuid', '=', $uuid]);
                } while( $existing > 0 && count($existing) > 0 );

                self::id($id)->update(['uuid' => $uuid]);
            }
        }
    }

    public static function onupdateUp($self) {
        $self->read(['up', 'instances_ids']);
        foreach($self as $server) {
            if(!$server['up']) {
                Instance::ids($server['instances_ids'])->update(['up' => false]);
            }
        }
    }
}
