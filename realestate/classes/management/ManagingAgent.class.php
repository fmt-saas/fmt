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
        return 'A managing agent is contractually in charge of the administration of one or more condominiums.';
    }

    public static function getColumns() {
        return [
            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity .',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'realestate\management\ManagingAgent'
            ],

            'condominiums_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\Condominium',
                'foreign_field'     => 'managing_agent_id',
                'description'       => "Condominiums the managing agent is in charge of."
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
