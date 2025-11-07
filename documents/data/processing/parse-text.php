<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
[$params, $providers] = eQual::announce([
    'description'   => 'Attempt to retrieve relevant information from a raw text and return it as a partial map.',
    'help'          => 'Extracted values are meant to be used as complementary information for identifying a document or auto-completion of a document from a given type.',
    'params'        => [
        'text' =>  [
            'type'              => 'string',
            'usage'             => 'text/plain.medium',
            'description'       => 'Text as returned from the extraction of a document (.doc, .pdf, ...).',
            'required'          => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/json'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$text = $params['text'];


/*
    Possible properties returned in the Response :

    [invoice_number] => 744000399977
    [invoice_date] => 15/12/2024
    [customer_number] => 1000328782
    customer_reference
    [contract_number] =>
    [installation_number] => 4000232058
    [consumption_address] => CHEE DE LOUVAIN 261, 1210 SAINT-JOSSE-TEN-NOODE
    [period_start] => 10/2024
    [period_end] => 12/2024
    [amount_htva] =>
    [invoice_date] => 15/12/2024
    [amount_htva] =>
    [amount_tva] =>
    [amount_tvac] => 1.115.00
    [due_date] => 14/01/2025
    [iban] => BE52 0960 1178 4309
    [payment_id] => +++810/4584/43280+++
*/


$toIsoDate = function (string $input): ?string {
    $input = trim($input);

    // Format moth/year : 10/2024 or 10-2024
    if (preg_match('/^(\d{2})[\/\-](\d{4})$/', $input, $m)) {
        return "{$m[2]}-{$m[1]}-01T00:00:00Z";
    }

    // Format day/month/year: 01/10/2024 or 01-10-2024
    if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $input, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}T00:00:00Z";
    }

    // Format year-month : 2024-10
    if (preg_match('/^(\d{4})-(\d{2})$/', $input, $m)) {
        return "{$m[1]}-{$m[2]}-01T00:00:00Z";
    }

    $ts = strtotime($input);
    return $ts ? date('Y-m-01\T00:00:00\Z', $ts) : null;
};

$output = [];

$patterns = [
    'invoice_number' => [
        '/facture\s+[^0-9]*([A-Z0-9\/\-]{4,})/i',
    ],

    'invoice_date' => [
        '/facture\s+[^0-9]*\d*\s+du\s+(\d{2}\/\d{2}\/\d{4})/i',
        '/date\s*[:]?\s*(\d{2}\/\d{2}\/\d{4})/i',
    ],

    'customer_number' => [
        '/[^0-9]*\sclient\s*[:\-]?\s*(\d{4,})/i',
    ],

    'customer_reference' => [
        '/\b(r[eé]f[ée]rence|ref)\s+client[e]?[:\-]?\s*([A-Z0-9\-\/]+)\b/i',
        '/\bclient[e]?\s+r[eé]f[ée]rence[:\-]?\s*([A-Z0-9\-\/]+)\b/i',
    ],

    'contract_number' => [
        '/[^0-9]*\scontrat\s*[:\-]?\s*(\d{4,})/i',
    ],

    'installation_number' => [
        '/installation\s*[:\-]?\s*(\d{3,})/mi',
        '/\s+EAN\s*[:\-]?\s*(\d{3,})/mi',
    ],

    'consumption_address' => [
        '/adresse\s+[^:]*:?[^A-Z]*([0-9A-Z ,-]*)/mi',
    ],

    'period_start' => [
        '/periode[\s\S]*?\s+de\s+(\d{2}\/\d{4})\s+/mi',
        '/periode[\s\S]*?\s+du\s+(\d{2}\/\d{4})\s+/mi'
    ],

    'period_end' => [
        '/periode[\s\S]*?a\s+(\d{2}\/\d{4})/mi',
        '/periode[\s\S]*?au\s+(\d{2}\/\d{4})/mi',
    ],

    'amount_htva' => [
        '/total de la facture\s*\(htva\)\s*([\d\s.,]+) ?€/i',
    ],

    'amount_tva' => [
        '/tva\s*\d{1,2}%\s*([\d\s.,]+) ?€/i',
    ],

    'amount_tvac' => [
        '/total de la facture\s*\(tvac\)\s*([\d\s.,]+) ?€/i',
        '/a payer\s*([\d\s.,]+) ?€/i',
    ],

    'due_date' => [
        '/(?:date\s+d[’\'e]ch[ée]ance|d[’\'e]ch[ée]ance|date\s+limite\s+de\s+paiement)\s*[:\-]?\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i',
        '/paiement\s+(avant|pour)\s+le\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i',
        '/avant\s+le\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i',
    ],

    // ex. BE38 0015 0942 4272
    'iban' => [
        '/(BE\d{2} ?\d{4} ?\d{4} ?\d{4})/i',
    ],

    // ex. +++140/3598/57438+++
    'payment_id' => [
        '/(\+{3}\d{3}\/\d{4}\/\d{5}\+{3})/',
    ],

    'seller_vat' => [
        '/(?:TVA|BTW|TVA\/BTW)\s*[:\-]?\s*(BE\s*0?\s*\d{3}[ .]?\d{3}[ .]?\d{3})/i',
        '/\b(BE\s*0?\s*\d{3}[ .]?\d{3}[ .]?\d{3})\b/i',
    ],

    'ean_code' => [
        '/ean\s*[:\-]?\s*(\d[\d\s]{11,}\d)/i',
        '/(5414[\d\s]{14})/i',
    ]
];

// additional specific treatments
foreach($patterns as $field => $regexList) {
    foreach($regexList as $regex) {
        if(preg_match($regex, $text, $match)) {
            $value = trim($match[1]);

            if(preg_match('/amount_/', $field)) {
                $value = str_replace([' ', ','], ['', '.'], $value);
            }
            elseif(preg_match('/period_/', $field)) {
                $value = $toIsoDate($value);
            }
            elseif($field === 'seller_vat') {
                $value = str_replace(' ', '', $value);
            }
            elseif($field === 'iban') {
                $value = str_replace(' ', '', $value);
            }

            $output[$field] = trim($value);
            break;
        }
    }
    if (!isset($output[$field])) {
        $output[$field] = null;
    }
}

$context->httpResponse()
        ->body($output)
        ->send();
