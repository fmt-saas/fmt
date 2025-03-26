<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace realestate\management;

use identity\Identity;

class ManagingAgent extends \identity\Organisation {

    public static function getName() {
        return 'Managing Agent';
    }

    public function getTable() {
        return 'realestate_management_managingagent';
    }

    public static function getDescription() {
        return 'A managing agent is contractually in charge of the administration of one or more condominiums.';
    }

    public static function getColumns() {
        return [
            'condominiums_ids' => [
                'type'              => 'one2many',
                'description'       => "Condominiums the managing agent is in charge of.",
                'foreign_object'    => 'realestate\property\Condominium',
                'foreign_field'     => 'managing_agent_id'
            ],

            'management_contracts_ids' => [
                'type'              => 'one2many',
                'description'       => "History of management contracts with Condominiums.",
                'foreign_object'    => 'realestate\management\ManagementContract',
                'foreign_field'     => 'managing_agent_id'
            ],

            /* #todo - complete sync with onupdate callbacks */

            'bank_account_iban' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'bank_account_iban'],
            ],

            'bank_account_bic' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'bank_account_bic'],
            ],

            'legal_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'legal_name'],
            ],

            'short_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'short_name'],
            ],

            'has_vat' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'store'             => true,
                'relation'          => ['identity_id' => 'has_vat'],
            ],

            'vat_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'vat_number'],
            ],

            'registration_number' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'registration_number'],
            ],

            'address_street' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'address_street'],
                'onupdate'          => 'onupdateAddressStreet'
            ],

            'address_dispatch' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'address_dispatch'],
                'onupdate'          => 'onupdateAddressDispatch'
            ],

            'address_city' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'address_city'],
                'onupdate'          => 'onupdateAddressCity'
            ],

            'address_zip' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'address_zip'],
                'onupdate'          => 'onupdateAddressZip'
            ],

            'address_state' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'address_state'],
                'onupdate'          => 'onupdateAddressState'
            ],

            'address_country' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'address_country'],
                'onupdate'          => 'onupdateAddressCountry'
            ],

            'email' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'email'],
            ],

            'email_alt' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'email_alt'],
            ],

            'phone' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'phone'],
            ],

            'phone_alt' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'phone_alt'],
            ],

            'mobile' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'mobile'],
            ],

            'fax' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'fax'],
            ],

            'website' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'relation'          => ['identity_id' => 'website'],
            ],

        ];
    }

    public static function onupdateAddressStreet($self) {
        self::updateField($self, 'address_street');
    }

    public static function onupdateAddressDispatch($self) {
        self::updateField($self, 'address_dispatch');
    }

    public static function onupdateAddressCity($self) {
        self::updateField($self, 'address_city');
    }

    public static function onupdateAddressZip($self) {
        self::updateField($self, 'address_zip');
    }

    public static function onupdateAddressState($self) {
        self::updateField($self, 'address_state');
    }

    public static function onupdateAddressCountry($self) {
        self::updateField($self, 'address_country');
    }

    private static function updateField($self, $field) {
        $self->read(['identity_id', $field]);
        foreach($self as $id => $managingAgent) {
            if($managingAgent['identity_id']) {
                Identity::id($managingAgent['identity_id'])->update([$field => $managingAgent[$field]]);
            }
        }
    }

}
