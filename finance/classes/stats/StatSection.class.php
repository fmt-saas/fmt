<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\stats;
use equal\orm\Model;

class StatSection extends Model {

    public static function getName() {
        return "Statistics Section";
    }

    public static function getDescription() {
        return "Stat sections allow to generate view by grouping sales in an arbitray manner (independent from chart of accounts and analytical chart of accounts).";
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
                'description'       => "Short description of the section."
            ],

            'label' => [
                'type'              => 'string',
                'description'       => "The label of the section."
            ],

            /* parent chart of accounts */
            'stat_chart_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\stats\StatChart',
                'description'       => "The stats chart the line belongs to.",
                'required'          => true
            ]

        ];
    }


    public function getUnique() {
        return [
            ['code']
        ];
    }


}