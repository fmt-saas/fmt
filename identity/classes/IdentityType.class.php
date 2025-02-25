<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;
use equal\orm\Model;

class IdentityType extends Model {

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'alias',
                'alias'             => 'description'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Mnemonic of the identity type.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the identity type.",
                "multilang"         => true
            ]

        ];
    }

}