<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class AssemblyInvitation extends \equal\orm\Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ],

            'owner_id' => [
                'type'              => 'many2one',
                'description'       => "The owner concerned by the invitation.",
                'help'              => 'A single invite is generated for each Ownership (representative).',
                'foreign_object'    => 'realestate\ownership\Owner',
                'required'          => true
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership concerned by the invitation.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
            ],

            'sent_date' => [
                'type'              => 'string',
                'description'       => "Date at which the original (first) invite was sent.",
                'help'              => 'This date is immutable (@see `canupdate`). The original date must remain the same in case of multiple generation.'
            ],

            'communication_method' => [
                'type'              => 'string',
                'selection'         => [
                    'email',
                    'postal',
                    'postal_registered',
                    'postal_registered_receipt'
                ],
                'description'       => "Method used to send the invitation.",
                'default'           => 'postal_registered'
            ],

            'is_sent' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the invitation has been acknowledged by the owner.",
                'default'           => false
            ],

            'is_acknowledged' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the invitation has been acknowledged by the owner.",
                'default'           => false
            ]

        ];
    }

    protected static function canupdate($self, $values) {
        $self->read(['is_sent']);
        foreach($self as $id => $assemblyInvitation) {
            if(isset($values['sent_date']) && $self['is_sent']) {
                return ['sent_date' => ['not_allowed' => 'Sent date cannot be changed after first sending.']];
            }
        }
        return [];
    }
}
