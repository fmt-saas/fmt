<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class AssemblyRepresentation extends \equal\orm\Model {

    public static function getDescription() {
        return "Represents a validated link between a person physically attending a general assembly and an ownership (group of lots) they are authorized to represent for that assembly.
        Each representation is considered confirmed only after verification of legal validity (e.g., owner present, valid proxy, indivision rules). Used to generate the attendance sheet, determine quorum and voting rights. Only confirmed representations are taken into account.";
    }

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
                'description'       => "The assembly the proxy refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ],

            'attendee_id' => [
                'type'              => 'many2one',
                'description'       => "Attendee holder of the proxy.",
                'foreign_object'    => 'realestate\governance\AssemblyAttendee',
                'required'          => true,
                'dependents'        => ['identity_id']
            ],

            'ownership_id' => [
                'type'              => 'many2one',
                'description'       => "The ownership that is represented by the proxy.",
                'foreign_object'    => 'realestate\ownership\Ownership',
                'required'          => true
            ],

            'representation_type' => [
                'type'              => 'string',
                'selection'         => [
                    'owner',
                    'proxy'
                ],
                'description'       => "The way the ownership is represented.",
                'required'          => true
            ],

            'assembly_mandate_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\governance\AssemblyMandate',
                'description'       => "Proxy the representation originates from, if any.",
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['attendee_id', '=', 'object.attendee_id'], ['ownership_id', '=', 'object.ownership_id']],
                'visible'           => ['representation_type', '=', 'proxy']
            ],

            'mandate_shares' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'relation'          => ['assembly_mandate_id' => 'mandate_shares'],
                'description'       => "Computed weight of the vote, based on shares and majority type (via assembly_item_id).",
                'store'             => false
            ]

        ];
    }

}
