<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\bank;

use equal\data\DataFormatter;
use equal\orm\Model;
use identity\Organisation;

class BankAccount extends Model {

    public static function getColumns() {

        return [
            'condo_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'realestate\property\Condominium',
                //'readonly'          => true
                'visible'           => ['organisation_id', '=', null]
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => 'The display name of the account (IBAN).',
                'store'             => true,
                'instant'           => true
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organization that owns the bank account.',
                'dependents'        => ['name'],
                'ondelete'          => 'cascade',
                'visible'           => ['condo_id', '=', null]
            ],

            'bank_country' => [
                'type'              => 'computed',
                'function'          => 'calcBankCountry',
                'result_type'       => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'The country where the organization holds the bank account, specified using the ISO 3166-2 code.',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true
            ],

            'bank_name' => [
                'type'              => 'string',
                'description'       => 'The name of the bank where the organization holds its account.'
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn:iban',
                'description'       => 'The IBAN number of the organization’s bank account.',
                'dependents'        => ['name','bank_country'],
                'required'          => true,
                'onupdate'          => 'onupdateBankAccountIban'
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => 'The BIC code of the bank related to the organization’s bank account.',
                'onupdate'          => 'onupdateBankAccountBic'
            ]

        ];
    }


    public static function onupdateBankAccountIban($self) {
        $self->read(['organisation_id', 'bank_account_iban', 'condo_id']);
        foreach($self as $id => $bankAccount) {
            // ignore condominiums accounts
            if($bankAccount['condo_id']) {
                continue;
            }
            $organisation = Organisation::id($bankAccount['organisation_id'])->read(['id', 'bank_account_ids'])->first();
            if($organisation) {
                // by convention, if current bank account is the first of the organisation, sync back with iban from organisation
                $first_bank_account_id = min($organisation['bank_account_ids']);
                if($id == $first_bank_account_id) {
                    Organisation::id($bankAccount['organisation_id'])
                       ->update([
                           'bank_account_iban' => $bankAccount['bank_account_iban']
                       ]);
               }
            }
        }
    }

    public static function onupdateBankAccountBic($self) {
        $self->read(['organisation_id', 'bank_account_bic', 'condo_id']);
        foreach($self as $id => $bankAccount) {
            // ignore condominiums accounts
            if($bankAccount['condo_id']) {
                continue;
            }
            $organisation = Organisation::id($bankAccount['organisation_id'])->read(['id', 'bank_account_ids'])->first();
            if($organisation) {
                // by convention, if current bank account is the first of the organisation, sync back with iban from organisation
                $first_bank_account_id = min($organisation['bank_account_ids']);
                if($id == $first_bank_account_id) {
                    Organisation::id($bankAccount['organisation_id'])
                       ->update([
                           'bank_account_bic' => $bankAccount['bank_account_bic']
                       ]);
               }
            }
        }
    }

    public static function onchange($event, $values, $lang) {
        $result = [];

        if(isset($event['bank_account_iban'])) {
            $result['bank_country'] = self::computeCountryFromIban($event['bank_account_iban']);
            $bank_info = self::computeBankFromIban($event['bank_account_iban'], $lang);
            if($bank_info) {
                $result['bank_name'] = $bank_info['name'];
                $result['bank_account_bic'] = $bank_info['bic'];
            }
        }

        return $result;
    }

    public static function calcBankCountry($self) {
        $result = [];
        $self->read(['bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            $result[$id]  = self::computeCountryFromIban($bankAccount['bank_account_iban']);
        }
        return $result;
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['organisation_id', 'condo_id', 'bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            if($bankAccount['bank_account_iban'] && strlen($bankAccount['bank_account_iban']) > 0) {
                $result[$id] = DataFormatter::format($bankAccount['bank_account_iban'], 'iban');
            }
        }
        return $result;
    }

    public static function candelete($self) {
        $self->read(['organisation_id']);
        foreach($self as $bankAccount) {
            $organisation = Organisation::id($bankAccount['organisation_id'])->read(['bank_account_ids'])->first();
            if(count($organisation['bank_account_ids']) <= 1 ) {
                return ['id' => ['non_removable' => 'The bank account cannot be removed. Organizations must have at least one bank account.']];
            }
        }
        return parent::candelete($self);
    }

    private static function computeCountryFromIban($iban) {
        $country = '';
        if($iban && strlen($iban) > 0) {
            $country = substr($iban, 0, 2);
        }
        return $country;
    }

    private static function computeBankFromIban($iban, $lang) {
        $result = null;

        $normalized_iban = str_replace(' ', '', trim($iban));

        if(preg_match('/^[A-Z]{2}\d{2}\d{12}$/', $normalized_iban)) {
            $country = substr($normalized_iban, 0, 2);
            $bank_code = substr($normalized_iban, 4, 3);

            $file = EQ_BASEDIR."/packages/identity/i18n/{$lang}/bic/{$country}.json";
            if(file_exists($file)) {
                $data = file_get_contents($file);
                $map_bank = json_decode($data, true);
                $result = $map_bank[$bank_code] ?? null;
            }
        }

        return $result;
    }


}
