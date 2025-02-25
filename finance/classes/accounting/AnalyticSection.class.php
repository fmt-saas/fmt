<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AnalyticSection extends Model {

    public static function getName() {
        return "Analytic Section";
    }

    public static function getDescription() {
        return "Analytic sections allow to group expenses and revenues independently from the chart of accounts.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'alias',
                'alias'             => 'code'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Unique code identifying the section.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the section.",
            ],

            'section_type' => [
                'type'              => 'string',
                'description'       => "Can the section be updated.",
                'selection'         => [
                    'costs',
                    'profit'
                ],
                'default'           => 'costs'
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => "Can the section be updated.",
                'default'           => false
            ],

            'label' => [
                'type'              => 'string',
                'description'       => "Short description of the section."
            ],

            'analytic_chart_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AnalyticChart',
                'description'       => "The analytic chart the section belongs to.",
                'required'          => true
            ]

        ];
    }

}
