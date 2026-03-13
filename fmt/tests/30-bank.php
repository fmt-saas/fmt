<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '3001' => [
        'description' => "Validate CODA import.",
        'help'        => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');
            return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $data]);
        },
        'assert'      => function ($statements) use ($providers) {
            $valid = true;
            foreach($statements as $statement) {
                $data = eQual::run('get', 'json-validate', ['json' => json_encode($statement), 'schema_id' => 'urn:fmt:json-schema:finance:bank-statement']);
                $valid &= $data['result'] ?? false;
            }
            return $valid;
        },
        'rollback'    => function () use ($providers) {
        }
    ],

    '3002' => [
        'description' => "Validate ISABEL XLSX import.",
        'help'        => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_isabel.xlsx');
            return eQual::run('get', 'finance_bank_BankStatement_parse-xls', ['data' => base64_encode($data)]);
        },
        'assert'      => function ($statements) use ($providers) {
            $valid = true;

            foreach($statements as $i => $statement) {
                $data = eQual::run('get', 'json-validate', ['json' => json_encode($statement), 'schema_id' => 'urn:fmt:json-schema:finance:bank-statement']);
                $valid &= $data['result'] ?? false;
            }
            return $valid;
        },
        'rollback'    => function () use ($providers) {
        }
    ],

    '3003' => [
        'description' => "Check CODA import.",
        'help'        => "Convert a CODA bank statement to standardized JSON and validate result data.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');
            return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $data]);
        },
        'assert'      => function ($statements) use ($providers) {
            if(count($statements) !== 1) {
                return false;
            }

            $statement = $statements[0];

            $transactions = $statement['transactions'];
            if(count($transactions) !== 5) {
                return false;
            }

            return count($statements) === 1
                && $statement['statement_number'] === '39'
                && $statement['account_iban'] === 'BE88191156749841'
                && $statement['bank_bic'] === 'CREGBEBB'
                && $statement['account_holder'] === 'KALEO - CENTRE BELGE TOURI'
                && $statement['account_type'] === 'current'
                && $statement['opening_balance'] === 11581.24
                && $statement['opening_date'] === '2020-03-21T00:00:00+00:00'
                && $statement['closing_balance'] === 13646.05
                && $statement['closing_date'] === '2011-01-11T00:00:00+00:00'
                && $statement['statement_currency'] === 'EUR'
                && $transactions[0]['amount'] === 19.8
                && $transactions[1]['amount'] === 44.45
                && $transactions[2]['amount'] === -479.04
                && $transactions[3]['amount'] === -479.04
                && $transactions[4]['amount'] === 63.74;
        },
        'rollback'    => function () use ($providers) {
        },
    ]

];

