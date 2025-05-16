<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Original author(s): Yesbabylon SA
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

        '1101' => [
            'description'       => "Validate CODA import.",
            'help'              => "Convert a CODA bank statement to standardized JSON and validate result against `purchase-invoice` schema.",
            'arrange'           => function() use($providers) {
                },
            'act'               => function() use($providers) {
                    $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/' . 'invoice_purchase.json');
                    return $data;
                },
            'assert'            => function($json_invoice) use($providers) {
                    $data = eQual::run('get', 'json-validate', ['json' => $json_invoice, 'schema_id' => 'urn:fmt:json-schema:finance:purchase-invoice']);
                    $valid = $data['result'] ?? false;
                    return $valid;
                },
            'rollback'          => function() use($providers) {
                }
        ]
];

