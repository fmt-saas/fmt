<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\customer;

use identity\Identity;

class Contact extends \identity\Contact {

    public static function getName() {
        return "Customer Contact";
    }

    public static function getDescription() {
        return "Customer contacts are persons, external to the organisation, that represent the customer or provide a link for information about the customer.";
    }

    public function getTable() {
        // force table name to use distinct tables and ID columns
        return 'sale_customer_contact';
    }

    public static function getColumns() {
        return [

            /**
             * Override identity Partner columns
             */

            'is_internal' => [
                'type'              => 'boolean',
                'description'       => 'The partnership relates to (one of) the organization(s) from the current installation.',
                'default'           => false,
                'readonly'          => true
            ],

            /**
             * Specific sale customer Contact columns
             */

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'Customer the contact relates to.',
                'required'          => true
            ]

        ];
    }

}
