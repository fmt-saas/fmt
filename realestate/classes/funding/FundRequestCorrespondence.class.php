<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace realestate\funding;

class FundRequestCorrespondence extends \documents\correspondence\DocumentCorrespondence {

    public static function getDescription() {
        return "Individual funding request.";
    }

    public static function getColumns() {

        return [
            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'realestate\funding\FundRequestCorrespondence'],
                'visible'           => ['communication_method', '=', 'email']
            ]
        ];
    }

}
