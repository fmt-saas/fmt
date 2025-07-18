<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
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
                'description'       => "Vote value: 'for', 'against', or 'abstain'.",
                'selection'         => ['for', 'against', 'abstain'],
                'required'          => true
            ],

            'sent_method' => [
                'type'              => 'string',
                'selection'         => ['email', 'postal', 'postal_registered', 'postal_registered_receipt'],
                'description'       => "Method used to send the invitation.",
            ],

            'is_acknowledged' => [
                'type'              => 'boolean',
                'description'       => "Indicates whether the invitation has been acknowledged by the owner.",
            ]

        ];
    }
}
