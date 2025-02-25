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

            'fiscal_year_start' => [
                'type'              => 'date',
                'description'       => 'Date at which the fiscal year starts.'
            ],

            'fiscal_period_frequency' => [
                'type'              => 'one2many',
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


        ];
    }
}