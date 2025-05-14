<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;


$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

// retrieve bank-statement schema
$statement_schema = json_encode(eQual::run('get', 'finance_schemas_bank-statement'));

$tests = [

        '1101' => [
            'description'       => "Validate CODA import.",
            'help'              => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
            'arrange'           => function() use($providers) {
                },
            'act'               => function() use($providers) {
                    $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_coda.txt');
                    return eQual::run('get', 'finance_bank_BankStatement_parse-coda', ['data' => $data]);
                },
            'assert'            => function($statements) use($providers, $statement_schema) {
                    $valid = true;
                    foreach($statements as $statement) {
                        $validator = new Validator();
                        /** @var ValidationResult $result */
                        $result = $validator->validate((object) json_decode(json_encode($statement)), $statement_schema);
                        $valid &= $result->isValid();
                    }
                    return $valid;
                },
            'rollback'          => function() use($providers) {
                }
        ],
        '1102' => [
            'description'       => "Validate ISABEL XLSX import.",
            'help'              => "Convert a CODA bank statement to standardized JSON and validate result against `bank-statement` schema.",
            'arrange'           => function() use($providers) {
                },
            'act'               => function() use($providers) {
                    $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'bank_isabel.xlsx');
                    return eQual::run('get', 'finance_bank_BankStatement_parse-xls', ['data' => base64_encode($data)]);
                },
            'assert'            => function($statements) use($providers, $statement_schema) {
                    $valid = true;

                    foreach($statements as $i => $statement) {
                        $validator = new Validator();
                        /** @var ValidationResult $result */

                        $result = $validator->validate((object) json_decode(json_encode($statement)), $statement_schema);
                        $valid &= $result->isValid();
                    }
                    return $valid;
                },
            'rollback'          => function() use($providers) {
                }
        ]

];

