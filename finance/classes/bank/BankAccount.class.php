<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
namespace finance\bank;

use equal\data\DataFormatter;
use equal\orm\Model;
use identity\Identity;

class BankAccount extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'description'       => 'The display name of the account (IBAN).',
                'store'             => true,
                'instant'           => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the account (purpose).',
                'dependents'        => ['name']
            ],

            'bank_account_type' => [
                'type'              => 'string',
                'description'       => 'Type of bank account (current of savings).',
                'help'              => 'Identifiers of this list should match the operation_assignment codes used in the chart of Accounts.',
                'selection'         => [
                    'bank_current',
                    'bank_savings'
                ],
                'default'           => 'bank_current'
            ],

            'is_primary' => [
                'type'              => 'boolean',
                'description'       => 'Flag marking the account as primary account.',
                'help'              => 'When a primary account is updated, sync is automatically replicated on related identity (from `owner_identity_id`).',
                'default'           => false,
                'visible'           => ['bank_account_type', '=', 'bank_current']
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organization that owns the bank account.',
                'dependents'        => ['name'],
                'ondelete'          => 'cascade',
                'visible'           => ['condo_id', '=', null]
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'description'       => "The condominium the accounting entry refers to.",
                'foreign_object'    => 'identity\Identity'
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'The IBAN number of the bank account.',
                'help'              => 'The IBAN number is a unique identifier for the bank account. Example: BE54000000000097',
                'dependents'        => ['name', 'bank_country', 'bank_account_bic', 'bank_name'],
                // for individuals, several persons might share/have a bank account in common
                // 'unique'            => true
                'required'          => true,
                'onupdate'          => 'onupdateBankAccountIban'
            ],

            'bank_account_bic' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The BIC code of the bank related to the organization\'s bank account.',
                'function'          => 'calcBankAccountBic',
                'store'             => true
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
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The name of the bank where the organization holds its account.',
                'function'          => 'calcBankName',
                'store'             => true
            ]

        ];
    }


    public static function onupdateBankAccountIban($self) {
        $self->read(['owner_identity_id', 'bank_account_iban', 'is_primary']);
        foreach($self as $id => $bankAccount) {
            if($bankAccount['is_primary']) {
                Identity::id($bankAccount['owner_identity_id'])->update(['bank_account_iban' => $bankAccount['bank_account_iban']]);
            }
        }
    }

    public static function onchange($event, $values, $lang) {
        $result = [];

        if(isset($event['bank_account_iban'])) {
            $result['bank_account_iban'] = preg_replace('/[^A-Z0-9]/i', '', $event['bank_account_iban']);
            $result['bank_country'] = self::computeCountryFromIban($result['bank_account_iban']);
            $bank_info = self::computeBankFromIban($result['bank_account_iban'], $lang);
            if($bank_info) {
                $result['bank_name'] = $bank_info['name'];
                $result['bank_account_bic'] = $bank_info['bic'];
            }
        }

        if(isset($event['bank_account_type'])) {
            if($event['bank_account_type'] !== 'bank_current') {
                $result['is_primary'] = false;
            }
        }

        return $result;
    }

    public static function calcBankName($self, $lang) {
        $result = [];
        $self->read(['bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            $bank_info = self::computeBankFromIban($bankAccount['bank_account_iban'], $lang);
            if($bank_info) {
                $result[$id] = $bank_info['name'];
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

    public static function calcBankAccountBic($self, $lang) {
        $result = [];
        $self->read(['bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            $bank_info = self::computeBankFromIban($bankAccount['bank_account_iban'], $lang);
            if($bank_info) {
                $result[$id] = $bank_info['bic'];
            }
        }
        return $result;
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['description', 'organisation_id', 'condo_id', 'bank_account_iban']);
        foreach($self as $id => $bankAccount) {
            if($bankAccount['bank_account_iban'] && strlen($bankAccount['bank_account_iban']) > 0) {
                $parts = [];
                $parts[] = DataFormatter::format($bankAccount['bank_account_iban'], 'iban');
                if(strlen($bankAccount['description']) > 0) {
                    $parts[] = $bankAccount['description'];
                }
                $result[$id] = implode(' - ', $parts);
            }
        }
        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['owner_identity_id']);
        foreach($self as $id => $bankAccount) {
            if(isset($values['is_primary'])) {
                $bankAccounts = self::search(['owner_identity_id', '=', $bankAccount['owner_identity_id']])
                    ->read(['id', 'is_primary']);
                foreach($bankAccounts as $otherBankAccount) {
                    if($otherBankAccount['id'] == $id) {
                        continue;
                    }
                    if($otherBankAccount['is_primary']) {
                        return ['is_primary' => ['duplicate_primary' => 'Only one primary account can be defined.']];
                    }
                }
            }
            return parent::canupdate($self);
        }
    }

    public static function candelete($self) {
        $self->read(['is_primary']);
        foreach($self as $bankAccount) {
            if($bankAccount['is_primary']) {
                return ['id' => ['non_removable' => 'The primary bank account cannot be removed. Organizations must have at least one bank account.']];
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

    private static function computeBankFromIban($iban, $lang='en') {
        $result = null;

        $normalized_iban = strtoupper(str_replace(' ', '', trim($iban)));

        if(!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $normalized_iban)) {

            return null;
        }

        $country = substr($normalized_iban, 0, 2);

        $iban_formats = [
                'BE' => ['bank_pos' => 4, 'bank_len' => 3],
                'FR' => ['bank_pos' => 4, 'bank_len' => 5],
                'DE' => ['bank_pos' => 4, 'bank_len' => 8],
                'LU' => ['bank_pos' => 4, 'bank_len' => 3],
                'NL' => ['bank_pos' => 4, 'bank_len' => 4],
                // #todo - to complete
            ];

        if(!isset($iban_formats[$country])) {
            return null;
        }

        ['bank_pos' => $pos, 'bank_len' => $len] = $iban_formats[$country];
        $bank_code = substr($normalized_iban, $pos, $len);

        $file = EQ_BASEDIR . "/packages/identity/i18n/{$lang}/bic/{$country}.json";

        if(!file_exists($file)) {
            $file = EQ_BASEDIR . "/packages/identity/i18n/en/bic/{$country}.json";
        }
        if(!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        $map_bank = json_decode($data, true);
        $result = $map_bank[$bank_code] ?? null;

        return $result;
    }

    /**
     * Synchronize the primary bank account of the identity.
     *
     */
    public static function onafterupdate($self, $values) {
        $self->read(['is_primary', 'owner_identity_id', 'bank_account_iban', 'bank_account_bic']);
        foreach($self as $id => $bankAccount) {
            if($bankAccount['is_primary']) {
                Identity::id($bankAccount['owner_identity_id'])
                    ->update([
                        'bank_account_iban' => $bankAccount['bank_account_iban'],
                        'bank_account_bic'  => $bankAccount['bank_account_bic']
                    ]);
            }
        }
    }

}
