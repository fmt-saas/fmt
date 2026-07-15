<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class CouncilMember extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['owner_id' => 'name'],
                'description'       => 'Display name of the council member, inherited from the owner.',
                'store'             => true,
                'instant'           => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium for which the owner is appointed as council member.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'owner_id' => [
                'type'              => 'many2one',
                'description'       => "Owner appointed as member of the condominium council.",
                'foreign_object'    => 'realestate\ownership\Owner',
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'required'          => true,
                'dependents'        => ['name']
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcIsActive',
                'description'       => 'Indicates whether the council membership is currently active.',
                'store'             => true,
                'instant'           => true
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'Start date of the council membership.',
                'dependents'        => ['is_active']
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'End date of the council membership.',
                'dependents'        => ['is_active']
            ],


            // #deprecated
            'role' => [
                'type'              => 'string',
                'description'       => 'Role assigned to the owner in the condominium council.',
                'selection'         => [
                    'president',
                    'secretary',
                    'member'
                ],
                'default'           => 'member',
                'visible'           => false
            ]

        ];
    }


    protected static function calcIsActive($self) {
        $result = [];
        $self->read(['date_from', 'date_to']);
        $today = strtotime(date('Y-m-d', time()));
        foreach($self as $id => $member) {
            $result[$id] = true;
            if($member['date_from'] && $member['date_from'] > $today) {
                $result[$id] = false;
            }
            if($member['date_to'] && $member['date_to'] < $today) {
                $result[$id] = false;
            }
        }
        return $result;
    }

}
