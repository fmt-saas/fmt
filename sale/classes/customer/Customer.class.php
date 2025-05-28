<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\customer;

use identity\Identity;

class Customer extends \identity\Identity {

    public function getTable() {
        return 'sale_customer_customer';
    }

    public static function getName() {
        return 'Customer';
    }

    public static function getDescription() {
        return "A customer is a partner with whom the company carries out commercial sales operations.";
    }

    public static function getColumns() {

        return [
            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the current entity .',
                'help'              => 'This is required in order to display the relational fields accordingly.',
                'default'           => 'sale\customer\Customer'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the Owner.",
                'relation'          => ['identity_id' => 'name'],
                'store'             => true,
                'readonly'          => true
            ],

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "Rate class that applies to the customer.",
                'help'              => "The fare (rate) class allows for the automatic assignment of a price list or price calculation for the customer.",
                'default'           => 1,
                'readonly'          => true
            ],

            'customer_nature_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerNature',
                'description'       => 'Nature of the customer (map with rate classes).',
                'onupdate'          => 'onupdateCustomerNatureId'
            ],

            'customer_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerType',
                'description'       => "Type of customer (map with rate classes). Defaults to 'individual'.",
                'help'              => "If partner is a customer, it can be assigned a customer type",
                'default'           => 1,
                'onupdate'          => 'onupdateCustomerTypeId'
            ],

            'relationship' => [
                'type'              => 'string',
                'default'           => 'customer',
                'description'       => 'Force relationship to Customer'
            ],

            'address' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcAddress',
                'description'       => 'Main address from related Identity.'
            ],

            'ref_account' => [
                'type'              => 'string',
                'description'       => 'Arbitrary reference account number for identifying the customer in external accounting softwares.',
                'readonly'          => true
            ],

            'receivables_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\receivable\Receivable',
                'foreign_field'     => 'customer_id',
                'description'       => 'List receivables of the customer.'
            ],

            'sales_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\SaleEntry',
                'foreign_field'     => 'customer_id',
                'description'       => 'List sales entries of the customer.'
            ],

            'subscriptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\subscription\Subscription',
                'foreign_field'     => 'customer_id',
                'description'       => 'List subscriptions of the customer.'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'foreign_field'     => 'customer_id',
                'description'       => 'List invoices of the customer.'
            ],

            'customer_external_ref' => [
                'type'              => 'string',
                'description'       => 'External reference for the customer, if any.'
            ],

            'flag_latepayer' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the customer as bad payer.'
            ],

            'flag_damage' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the customer with a damage history.'
            ],

            'flag_nuisance' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the customer with a disturbances history.'
            ],

            // #memo - foreign_field cannot be used here, since it should be identity_id, which points back to current object's `id` instead of `identity_id`
            'bank_accounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankAccount',
                'description'       => 'List of the bank account of the supplier.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id']
            ],

            'addresses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Address',
                'description'       => 'List of addresses related to the supplier.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id']
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Contact',
                'description'       => 'List of contacts related to the supplier.',
                'domain'            => ['owner_identity_id', '=', 'object.identity_id']
            ]

        ];
    }

    public static function onupdateIdentityId($self) {
        $self->read(['identity_id']);
        foreach($self as $id => $customer) {
            if($customer['identity_id']) {
                Identity::id($customer['identity_id'])->update(['customer_id' => $id]);
            }
        }
    }

    public static function onupdateCustomerNatureId($self) {
        $self->read(['customer_nature_id' => ['rate_class_id', 'customer_type_id']]);
        foreach($self as $id => $customer) {
            if($customer['customer_nature_id']) {
                self::id($id)->update([
                        'rate_class_id'     => $customer['customer_nature_id']['rate_class_id'],
                        'customer_type_id'  => $customer['customer_nature_id']['customer_type_id']
                    ]);
            }
        }
    }

    public static function calcAddress($self) {
        $result = [];
        $self->read(['address_street', 'address_city']);
        foreach($self as $id => $customer) {
            $result[$id] = "{$customer['address_street']} {$customer['address_city']}";
        }
        return $result;
    }

    public static function onupdateCustomerTypeId($self) {
        $self->read(['customer_type_id']);

        foreach($self as $id => $customer) {
            // #memo - there is a strict equivalence between identity type and customer type (the only distinction is in the presentation)
            self::id($id)->update(['type_id' => $customer['customer_type_id']]);
        }
    }

    public static function onchange($self, $event, $values, $lang) {
        $result = parent::onchange($self, $event, $values, $lang);
        if(isset($event['type_id'])) {
            $result['customer_type_id'] = CustomerType::id($event['type_id'])->read(['id', 'name'])->first(true);
        }
        return $result;
    }
}
