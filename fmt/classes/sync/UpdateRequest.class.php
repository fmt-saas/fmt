<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace fmt\sync;

use equal\orm\Model;

class UpdateRequest extends Model {

    public static function getColumns() {
        return [

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Field name of the targeted object.',
                'required'          => true
            ],

            'request_date' => [
                'type'              => 'datetime',
                'description'       => 'Date at which the request was made.',
                'required'          => true
            ],

            'instance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\server\Instance',
                'description'       => "The instance the request originates from."
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => "The Managing agent the requests originates from.",
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'processed'
                ],
                'default'           => 'pending',
                'description'       => 'Current status of the update request.'
            ]

        ];
    }

}
