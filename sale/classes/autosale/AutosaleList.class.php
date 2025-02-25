<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\autosale;
use equal\orm\Model;

class AutosaleList extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Context the discount is meant to be used."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Reason for which the discount is meant to be used."
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date (included) at which the season starts.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Date (excluded) at which the season ends.",
                'required'          => true                
            ],
            
            'autosale_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\autosale\AutosaleLine',
                'foreign_field'     => 'autosale_list_id',
                'description'       => 'The lines that apply to the list.'
            ],

            'autosale_list_category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\autosale\AutosaleListCategory',
                'description'       => 'The autosale category the list belongs to.',
                'required'          => true
            ]
            
        ];
    }

}