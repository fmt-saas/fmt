<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class Condominium extends \identity\Organisation {

    public function getTable() {
        return 'realestate_property_condominium';
    }

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'relation'          => ['id'],
                'description'       => "Alias of the `id` field.",
                'help'              => "This is used to comply with the Role assignments at Access Control level.",
                'store'             => true
            ],

            'managing_agent_id' => [
                'type'              => 'many2one',
                'description'       => "The managing agent currently managing the condominium.",
                'help'              => "The managing agent or 'Syndic', is in charge of the condominium, and can be a single person or an agency.",
                'foreign_object'    => 'realestate\management\ManagingAgent',
                'required'          => true
            ],

            'role_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\role\RoleAssignment',
                'foreign_field'     => 'condo_id',
                'description'       => 'List of employees assigned to the management of the condominium.'
            ],

            'total_shares' => [
                'type'              => 'integer',
                'description'       => "The total number of shares of the ownership.",
                'default'           => 1000
            ],

            'construction_permit_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the permit was issued.'
            ],

            'construction_start_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the construction started.'
            ],

            'construction_compliance_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the compliancy documentation was issued.'
            ],

            'construction_completion_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the construction finished.'
            ],

            'condo_creation_date' => [
                'type'              => 'date',
                'description'       => 'Date at which the condominium was constituted.'
            ],

            'condo_regulations_date' => [
                'type'              => 'date',
                'description'       => 'Date of the latest update of the condominium regulations.'
            ],

            'cadastral_number' => [
                'type'              => 'string',
                'description'       => 'Number of the cadastral register of the property.',
            ],

            'fiscal_year_start' => [
                'type'              => 'date',
                'description'       => 'Date at which the fiscal year starts.'
            ],

            'fiscal_period_frequency' => [
                'type'              => 'string',
                'selection'         => [
                    'Q' => 'Quarterly',
                    'T' => 'Tertially' ,
                    'S' => 'Semi-Annually',
                    'A' => 'Annually'
                ],
                'description'       => 'List of employees assigned to the management of the condominium.',
                'default'           => 'Q'
                /*
                Quarterly (3 months)	4	Q (Q1, Q2, Q3, Q4)
                Tertially (4 months)	3	T (T1, T2, T3)
                Semi-Annually (6 months)2	S (S1, S2)
                Annually (12 months)	1	A (A1)
                */
            ],

            'common_areas_ids' => [
                'type'              => 'one2many',
                'description'       => "List of common areas in the condominium.",
                'foreign_object'    => 'realestate\property\CommonArea',
                'foreign_field'     => 'condo_id'
            ],

            'apportionment_keys_ids' => [
                'type'              => 'one2many',
                'description'       => "The apportionment keys relating to the condominium.",
                'foreign_object'    => 'realestate\property\ApportionmentKey',
                'foreign_field'     => 'condo_id'
            ]

        ];
    }
}