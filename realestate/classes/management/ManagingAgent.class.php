<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace realestate\management;

use equal\data\DataGenerator;
use identity\Identity;

class ManagingAgent extends \purchase\supplier\Supplier {

    // #memo ManagingAgent uses the same DB table as Supplier

    public static function constants() {
        return ['FMT_INSTANCE_TYPE'];
    }

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
            'instance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'infra\server\Instance',
                'description'       => "The instance on which the condominium is currently managed."
            ],

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
                ],
                'default'           => 'professional'
            ],

            'professional_registration_number' => [
                'type'              => 'string',
                'description'       => 'Official number assigned by a licensing or registration authority to authorize the exercise of a regulated profession.',
                'help'              => 'Ex. IPI number (Belgium), professional card (France), license number (other countries).',
            ],

            'condominiums_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\property\Condominium',
                'foreign_field'     => 'managing_agent_id',
                'description'       => "Condominiums the managing agent is currently in charge of."
            ],

            'management_contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\management\ManagingAgentContract',
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
}
