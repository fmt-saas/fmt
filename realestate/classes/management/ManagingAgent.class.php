<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\management;

use identity\Identity;

class ManagingAgent extends \purchase\supplier\Supplier {

    public static function getName() {
        return 'Managing Agent';
    }

    public static function getDescription() {
        return 'A managing agent is contractually in charge of the administration of a Condominium.
        Each instance is expected to hold a single `active` Managing Agent, which should relate to the instance Organisation (through `identity_id`).
        So that Employees relate to the Managing Agent as well.
        Other Managing Agents can be present in order to maintain history data.';
    }

    public static function getColumns() {
        return [
            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity .',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'realestate\management\ManagingAgent'
            ],

            'agent_identity_type' => [
                'type'              => 'string',
                'selection'         => [
                    'owner',
                    'professional'
                ]
            ],

            'professional_registration_number' => [
                'type'              => 'string',
                'description'       => 'Official number assigned by a licensing or registration authority to authorize the exercise of a regulated profession.',
                'help'              => 'Ex. IPI number (Belgium), professional card (France), license number (other countries).',
            ],

            'management_contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\management\ManagementContract',
                'foreign_field'     => 'managing_agent_id',
                'description'       => "History of management contracts with Condominiums."
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
        parent::onupdateIdentityId($self);
    }

}
