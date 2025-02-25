<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountChart extends Model {
    
    public static function getName() {
        return "Chart of Accounts";
    }

    public static function getDescription() {
        return "Chart of Accounts is an organisational list holding all company's financial accounts.";
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the chart of accounts."
            ],

            /* owner organisation */
            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organisation the chart belongs to.",
                'required'          => true
            ],

            'account_chart_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\accounting\AccountChartLine',
                'foreign_field'     => 'account_chart_id',
                'description'       => 'Account lines that belong to the chart.',
                'ondetach'          => 'delete'
            ]

        ];
    }

}