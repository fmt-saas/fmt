<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\governance;

class AssemblyMinutesCorrespondence extends \documents\correspondence\DocumentCorrespondence {

    public function getTable() {
        return 'realestate_governance_assemblyminutescorrespondence';
    }

    public static function getDescription() {
        return "Individual minutes report of a General Assembly.";
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

            'document_id' => [
                'type'              => 'many2one',
                'description'       => 'The document (PDF) of the invitation, if any.',
                'foreign_object'    => 'documents\Document',
                'onupdate'          => 'onupdateDocumentId',
                'visible'           => [['has_document', '=', true], ['communication_method', '<>', 'email']]
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'realestate\governance\AssemblyMinutesCorrespondence'],
                'visible'           => ['communication_method', '=', 'email']
            ]
        ];
    }

    protected static function onupdateDocumentId($self) {
        $self->read(['document_id']);
        foreach($self as $id => $assemblyInvitation) {
            self::id($id)->update(['has_document' => (bool) $assemblyInvitation['document_id']]);
        }
    }

}
