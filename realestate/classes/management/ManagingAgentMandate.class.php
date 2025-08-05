<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\management;

class ManagingAgentMandate extends \equal\orm\Model {

    public static function getName() {
        return 'Managing Agent Mandate';
    }


    public static function getDescription() {
        return 'A managing agent mandate is issued during a general assembly and remains valid until revoked.';
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
                'description'       => "Managing agent the mandate relates to.",
                'required'          => true
            ],

            'contract_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\management\ManagingAgentContract',
                'description'       => "Contract the mandate relates to, if any.",
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Start of mandate validity period (date of election).",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "End of mandate validity period.",
                'help'              => "This is expected to be the date of the next general assembly. But a mandate can be revoked during an extraordinary general assembly.",
                'required'          => true
            ],

            'termination_date' => [
                'type'              => 'date',
                'description'       => "Actual end of the contract.",
                'help'              => "This can differ from date_to in case of anticipated termination.",
            ],

            'termination_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'mandate_expired'       => "Mandate expired (end of term)",
                    'ag_decision'           => "Terminated by decision of the General Assembly",
                    'court_decision'        => "Terminated by court decision",
                    'mutual_agreement'      => "Termination by mutual agreement",
                    'breach_of_contract'    => "Termination for breach of contract",
                    'non_performance'       => "Termination for non-performance of duties",
                    'voluntary_resignation' => "Voluntary resignation of the managing agent",
                    'company_closure'       => "Closure or liquidation of the managing agent's company",
                    'legal_incapacity'      => "Incapacity of the managing agent",
                    'other'                 => "Other"
                ]
            ],

            'appointment_type' => [
                'type'              => 'string',
                'description'       => 'The way the managing agent was appointed.',
                'selection' => [
                    'elected',
                    'court_appointed',
                    'provisional'
                ]
            ],

            'appointment_assembly_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\governance\Assembly',
                'visible'           => ['appointment_type', '=', 'elected']
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Current state of the mandate.",
                'default'           => true
            ],

        ];
    }

}

