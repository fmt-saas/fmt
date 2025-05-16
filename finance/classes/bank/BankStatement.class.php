<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace finance\bank;

use equal\orm\Model;

class BankStatement extends Model {

    public static function getName() {
        return 'Bank statement';
    }

    public static function getDescription() {
        return 'A bank statement summarizes a bank account financial transactions over a period.'
            .' It includes details of deposits, withdrawals, fees, and the account balance.';
    }

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
                'description'       => 'Display name of bank statement.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'raw_data'  => [
                'type'              => 'binary',
                'description'       => 'Original file used for creating the statement.'
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date the statement was received.',
                'required'          => true,
                'readonly'          => true
            ],

            'statement_currency' => [
                'type'              => 'string',
                'description'       => 'Currency of the statement.',
                'default'           => 'EUR'
            ],

            'opening_date' => [
                'type'              => 'date',
                'description'       => 'Date the statement was received.',
                'required'          => true,
                'readonly'          => true
            ],

            'closing_date' => [
                'type'              => 'date',
                'description'       => 'Date the statement was received.',
                'required'          => true,
                'readonly'          => true
            ],

            'opening_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance before the transactions.',
                'required'          => true,
                'readonly'          => true
            ],

            'closing_balance' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Account balance after the transactions.',
                'required'          => true,
                'readonly'          => true
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'IBAN representation of the account number.',
                'required'          => true
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => 'Bank Identification Code of the account.'
            ],

            'statement_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'foreign_field'     => 'bank_statement_id',
                'description'       => 'The lines that are assigned to the statement.'
            ],

            'status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcStatus',
                'selection'         => [
                    'pending',                // hasn't been fully processed yet
                    'reconciled'              // has been fully processed (all lines either ignored or reconciled)
                ],
                'description'       => 'Status of the statement (depending on lines).',
                'store'             => true
            ]


        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['bank_account_number', 'date', 'opening_balance', 'closing_balance']);
        foreach($statements as $oid => $statement) {
            $result[$oid] = sprintf("%s - %s - %s - %s", $statement['bank_account_number'], date('Ymd', $statement['date']), $statement['opening_balance'], $statement['closing_balance']);
        }
        return $result;
    }

    public static function calcBankAccountIban($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['bank_account_number', 'bank_account_bic']);

        foreach($statements as $oid => $statement) {
            $result[$oid] = self::convertBbanToIban($statement['bank_account_number']);
        }
        return $result;
    }

    public static function calcStatus($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['statement_lines_ids.status']);

        if($statements > 0) {
            foreach($statements as $sid => $statement) {
                $is_reconciled = true;
                foreach((array) $statement['statement_lines_ids.status'] as $lid => $line) {
                    if( !in_array($line['status'], ['reconciled', 'ignored']) ) {
                        $is_reconciled = false;
                        break;
                    }
                }
                $result[$sid] = ($is_reconciled)?'reconciled':'pending';
            }
        }
        return $result;
    }

    public static function convertBbanToIban($account_number) {

        /*
            account number already has IBAN format
        */

        if( !is_numeric(substr($account_number, 0, 2)) ) {
            return $account_number;
        }

        /*
            if code is not a country code, then convert BBAN to IBAN
        */

        // create numeric code of the target country
        $country_code = 'BE';

        $code_alpha = $country_code;
        $code_num = '';

        for($i = 0; $i < strlen($code_alpha); ++$i) {
            $letter = substr($code_alpha, $i, 1);
            $order = ord($letter) - ord('A');
            $code_num .= '1'.$order;
        }

        $check_digits = substr($account_number, -2);
        $dummy = intval($check_digits.$check_digits.$code_num.'00');
        $control = 98 - ($dummy % 97);
        return trim(sprintf("BE%02d%s", $control, $account_number));
    }


    public function getUnique() {
        return [
            ['bank_account_iban', 'opening_date', 'opening_balance', 'closing_balance']
        ];
    }
}