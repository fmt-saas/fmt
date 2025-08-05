<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\management;

class ManagingAgentContract extends \equal\orm\Model {

    public static function getName() {
        return 'Managing Agent Contract';
    }

    public static function getDescription() {
        return 'A managing agent is contractually in charge of the administration of a Condominium.';
    }

    public static function getColumns() {
        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the property lot belongs to.",
                'foreign_object'    => 'realestate\property\Condominium',
                'required'          => true
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'description'       => "Managing agent the contract relates to.",
            ],

            'contract_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'Contract document, signed by both parties.',
                'domain'            => [['condo_id', '=', 'object.condo_id'], ['condo_id', '<>', null]]
            ],

            'indexation_rate' => [
                'type'              => 'float',
                'description'       => "Rate for yearly indexation of the condominium's fees.",
                'default'           => 0.0
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Start of validity period.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "End of validity period.",
                'help'              => "Duration can either be set in advance or keep running while not statuted by the general assembly. This is a theoretical date: the contract can be revoked by a general assembly.",
                'required'          => true
            ],

            'max_duration' => [
                'type'              => 'integer',
                'description'       => "In case it applies, this is the maximum duration of the contract, in years.",
                'help'              => "In Belgium, the duration of a management contract cannot exceed 3 years.",
                'default'           => 3
            ],

            'mandates_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\management\ManagingAgentMandate',
                'foreign_field'     => 'contract_id',
                'description'       => "List of mandates relating to the Contract.",
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Current state of the contract.",
                'default'           => true
            ]

        ];
    }

}

