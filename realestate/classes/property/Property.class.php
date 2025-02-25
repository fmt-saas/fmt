<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate\property;

class Property extends \identity\Establishment {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the property.",
                'required'          => true
            ]

        ];
    }
}