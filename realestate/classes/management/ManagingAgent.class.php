<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\management;

class ManagingAgent extends \identity\Organisation {

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
            ]
        ];
    }

}
