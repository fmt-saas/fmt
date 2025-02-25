<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pos;
use equal\orm\Model;

class Cashdesk extends Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short mnemo to identify the cashdesk.",
                'required'          => true
            ],

            'establishment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => \identity\Establishment::getType(),
                'description'       => "The center the desk relates to.",
                'required'          => true,
                'ondelete'          => 'cascade'         // delete cashdesk when parent Center is deleted
            ],

            'sessions_ids'  => [
                'type'              => 'one2many',
                'foreign_object'    => CashdeskSession::getType(),
                'foreign_field'     => 'cashdesk_id',
                'ondetach'          => 'delete',
                'description'       => 'List of sessions of the cashdesk.'
            ]
        ];
    }

}