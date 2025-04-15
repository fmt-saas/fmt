<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\management;

use identity\Identity;

class ManagingAgent extends \identity\Identity {

    public static function getName() {
        return 'Managing Agent';
    }

    public function getTable() {
        return 'realestate_management_managingagent';
    }

    public static function getDescription() {
        return 'A managing agent is contractually in charge of the administration of one or more condominiums.';
    }

    public static function getColumns() {
        return [
            'condominiums_ids' => [
                'type'              => 'one2many',
                'description'       => "Condominiums the managing agent is in charge of.",
                'foreign_object'    => 'realestate\property\Condominium',
                'foreign_field'     => 'managing_agent_id'
            ],

            'management_contracts_ids' => [
                'type'              => 'one2many',
                'description'       => "History of management contracts with Condominiums.",
                'foreign_object'    => 'realestate\management\ManagementContract',
                'foreign_field'     => 'managing_agent_id'
            ]

        ];
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $managingAgent) {
            if($managingAgent['identity_id']) {
                Identity::id($managingAgent['identity_id'])->update(['managing_agent_id' => $id]);
            }
        }
    }

}
