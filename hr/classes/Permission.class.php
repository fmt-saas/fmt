<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace hr;

class Permission extends \equal\orm\Model {

    public static function getColumns() {
        return [

            'role_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\role\Role',
                'foreign_field'     => 'permissions_ids',
                'description'       => "Targeted role to which the permission applies.",
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'class_name' => [
                'type'              => 'string',
                'description'       => 'Full name of the entity to which the permission rule applies.',
                'selection'         => [
                    'documents\processing\DocumentProcess' => 'Document Process'
                ],
                'required'          => true
            ],

            'transition' => [
                'type'              => 'string',
                'description'       => "Name of the workflow transition the permission applies to, if any.",
                'default'           => NULL
            ]
        ];
    }

    public static function onchange($event, $values, $view) {
        $result = [];

        if(isset($event['class_name'])) {
            if(class_exists($event['class_name'])) {
                $workflow = $event['class_name']::getWorkflow();
                $result['transition']['selection'] = array_keys($workflow);
            }
        }

        return $result;
    }

}
