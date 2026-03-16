<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

$providers = eQual::inject(['context', 'orm', 'auth', 'access']);

$tests = [

    '3101' => [
        'description' => "Validate purchase invoice import.",
        'help'        => "Convert a purchase invoice to standardized JSON and validate result against `purchase-invoice` schema.",
        'arrange'     => function () use ($providers) {
        },
        'act'         => function () use ($providers) {
            $data = file_get_contents(EQ_BASEDIR.'/packages/fmt/tests/'.'invoice_purchase.json');
            return $data;
        },
        'assert'      => function ($json_invoice) use ($providers) {
            $data = eQual::run('get', 'json-validate', ['json' => $json_invoice, 'schema_id' => 'urn:fmt:json-schema:finance:purchase-invoice']);
            $valid = $data['result'] ?? false;
            return $valid;
        },
        'rollback'    => function () use ($providers) {
        }
    ]
];

