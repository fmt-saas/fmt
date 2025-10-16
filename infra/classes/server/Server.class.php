<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace infra\server;

use equal\orm\Model;

class Server extends Model {


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

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short description of the Server.',
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


}
