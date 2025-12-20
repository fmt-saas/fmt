<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class AssemblyInvitationCorrespondence extends \documents\correspondence\DocumentCorrespondence {

    public function getTable() {
        return 'realestate_governance_assemblyinvitationcorrespondence';
    }

    public static function getDescription() {
        return "Individual invitation to a General Assembly. A convocation to the General Assembly generates at least one invitation per ownership (one invitation per ownership representative).";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['assembly_id' => 'name'],
                'store'             => true
            ],

            'assembly_id' => [
                'type'              => 'many2one',
                'description'       => "The assembly the invitation refers to.",
                'foreign_object'    => 'realestate\governance\Assembly',
                'required'          => true
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'realestate\governance\AssemblyInvitationCorrespondence'],
                'visible'           => ['communication_method', '=', 'email']
            ]
        ];
    }

}
