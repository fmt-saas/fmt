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
                'store'             => true,
                'instant'           => true
            ],

            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the Council Member belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'owner_id' => [
                'type'              => 'many2one',
                'description'       => "Owner linked to the membership.",
                'foreign_object'    => 'realestate\ownership\Owner',
                'domain'            => [['condo_id', '=', 'object.condo_id']],
                'required'          => true,
                'dependents'        => ['name']
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcIsActive',
                'description'       => 'Does the vote relate to a choice.',
                'store'             => true,
                'instant'           => true
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => 'Date at which the construction finished.',
                'dependents'        => ['is_active']
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'Date at which the construction finished.',
                'dependents'        => ['is_active']
            ],

            'role' => [
                'type'              => 'string',
                'description'       => 'Role assigned to the Owner in the Council.',
                'selection'         => [
                    'president',
                    'secretary',
                    'member'
                ],
                'default'           => 'member'
            ]

        ];
    }


    protected static function calcIsActive($self) {
        $result = [];
        $self->read(['date_from', 'date_to']);
        $today = strtotime(date('Y-m-d', time()));
        foreach($self as $id => $member) {
            $result[$id] = true;
            if($member['date_to'] && $member['date_to'] < $today) {
                $result[$id] = false;
            }
        }
        return $result;
    }

}
