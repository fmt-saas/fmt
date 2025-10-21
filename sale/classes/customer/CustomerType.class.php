<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace sale\customer;
use equal\orm\Model;

class CustomerType extends Model {

    public static function getDescription() {
        return 'Types of customer are for example: Individual, Self-Employed, Company, Non-profit organizations or Public Administration.'
            .' They are used to apply specific vat rate when items/services are sold to a type of user.';
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Display name of the customer type.',
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the customer type.",
                'multilang'         => true
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to this type of customer.",
                // #memo - when using Natures, the rate class to apply is set in CustomerNature
                'required'          => false
            ],
        ];
    }

}