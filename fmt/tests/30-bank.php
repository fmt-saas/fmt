<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

        '3001' => [
            'description'       => "Validate CODA import.",
            'help'              => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
            'arrange'           => function() use($providers) {
                },
            'act'               => function() use($providers) {
                    $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/' . 'bank_coda.txt');
                    return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $data]);
                },
            'assert'            => function($statements) use($providers) {
                    $valid = true;
                    foreach($statements as $statement) {
                        $data = eQual::run('get', 'json-validate', ['json' => json_encode($statement), 'schema_id' => 'urn:fmt:json-schema:finance:bank-statement']);
                        $valid &= $data['result'] ?? false;
                    }
                    return $valid;
                },
            'rollback'          => function() use($providers) {
                }
        ],
        '3002' => [
            'description'       => "Validate ISABEL XLSX import.",
            'help'              => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
            'arrange'           => function() use($providers) {
                },
            'act'               => function() use($providers) {
                    $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_isabel.xlsx');
                    return eQual::run('get', 'finance_bank_BankStatement_parse-xls', ['data' => base64_encode($data)]);
                },
            'assert'            => function($statements) use($providers) {
                    $valid = true;

                    foreach($statements as $i => $statement) {
                        $data = eQual::run('get', 'json-validate', ['json' => json_encode($statement), 'schema_id' => 'urn:fmt:json-schema:finance:bank-statement']);
                        $valid &= $data['result'] ?? false;
                    }
                    return $valid;
                },
            'rollback'          => function() use($providers) {
                }
        ]

];

