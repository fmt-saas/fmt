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

$normalize_number = function(string $input): ?float {
    // Detect if the value is negative (a '-' sign anywhere before the digits)
    $isNegative = preg_match('/-\s*\d/', $input) || str_starts_with(trim($input), '-');

    // Remove everything except digits, dots and commas
    $clean = preg_replace('/[^0-9.,]/', '', $input);

    if ($clean === '') {
        return null;
    }

    // If both separators are present
    if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
        // Remove all separators
        $digitsOnly = str_replace(['.', ','], '', $clean);

        // Reinsert a decimal separator 2 digits from the end if appropriate
        $intPart = substr($digitsOnly, 0, -2);
        $decPart = substr($digitsOnly, -2);
        $normalized = $intPart . '.' . $decPart;
    }
    // If only a comma (European format)
    elseif(strpos($clean, ',') !== false) {
        $normalized = str_replace('.', '', $clean);
        $normalized = str_replace(',', '.', $normalized);
    }
    // If only a dot (Anglo-Saxon format)
    else {
        $normalized = str_replace(',', '', $clean);
    }

    // Convert to float
    if(is_numeric($normalized)) {
        $value = (float)$normalized;
        return $isNegative ? -$value : $value;
    }

    return null;
};

$extract_address = function($text) {
    $result = null;

    $clean = str_replace('  ', ';', trim($text));
    $clean = preg_replace('/\s+/', ' ', $clean);

    // Expression régulière : détecte rue, code postal, ville
    $pattern = '/((?:\d+\s*(?:rue|avenue|boulevard|chaussee|place|allee|square|impasse)\s+[A-Za-z\'\-\s]+|'  // ex: "211 avenue Albert"
              . '(?:rue|avenue|boulevard|chaussee|place|allee|square|impasse)\s+[A-Za-z\'\-\s]+,\s*\d+))'  // ex: "avenue Albert, 211"
              . '.*?\s+(\d{4})\s+([A-Za-z\'\-]+)/im';

    if(preg_match($pattern, $clean, $matches)) {
        $result = trim($matches[1]) . ' ' . trim($matches[2]) . ' ' . trim($matches[3]);
    }

    return $result;
};

$output = [];

$patterns = [
    'invoice_number' => [
        // Exemples pris en charge :
        // "Facture n° 744000399977"
        // "Facture numero : 2024-00215"
        // "Facture ref : E24/03598574"
        // "Reference facture : E24/03598574"
        // "Invoice number : 2024-00215"
        // "Invoice Ref: 24/159-A"
        '/\bfacture\b[^\n]{0,20}?(?:n[o°]?|numero|ref(?:erence)?)?\s*[:\-]?\s*([A-Z]{0,3}\d{2,}[A-Z0-9\/\-]*)/i',
        '/\breference\s+facture\s*[:\-]?\s*([A-Z]{0,3}\d{2,}[A-Z0-9\/\-]*)/i'
    ],

    'invoice_date' => [
        '/\bfacture\s+[^0-9]*\d*\s+du\s+(\d{2}\/\d{2}\/\d{4})/i',
        '/\bdate\s*[:]?\s*(\d{2}\/\d{2}\/\d{4})/i',
    ],

    'customer_number' => [
        '/\b(?:numero|code)\s+client[^\nA-Z0-9]*([A-Z0-9\-\/]+)/i',
    ],

    'customer_reference' => [
        '/\b(?:reference|ref)\s+client[^\nA-Z0-9]*([A-Z0-9\-\/]+)/i',
        '/\bclient\s+(?:ref)[^\nA-Z0-9]*([A-Z0-9\-\/]+)/i'
    ],

    'contract_number' => [
        '/\b[^0-9]*\scontrat\s*[:\-]?\s*(\d{4,})/i',
    ],

    'installation_number' => [
        '/installation\s*[:\-]?\s*(\d{3,})/i',
        '/\s+EAN\s*[:\-]?\s*(\d{3,})/i',
    ],

    'consumption_address' => [
        // #memo - this might take several lines, in case of match an additional extract is required
        '/\badresse\s+(?:de)\s+(?:fourniture)([^:])*/im',
    ],

    'period_start' => [
        '/periode[\s\S]*?\s+de\s+(\d{2}\/\d{4})\s+/i',
        '/periode[\s\S]*?\s+du\s+(\d{2}\/\d{4})\s+/i',
        '/periode[\s\S]*?\s+du\s+(\d{2}\/\d{2}\/\d{4})\s+/i',
    ],

    'period_end' => [
        '/periode[\s\S]*?a\s+(\d{2}\/\d{4})/i',
        '/periode[\s\S]*?au\s+(\d{2}\/\d{4})/i',
        '/periode[\s\S]*?au\s+(\d{2}\/\d{2}\/\d{4})/i'
    ],

    'amount_htva' => [
        '/\btotal.*(?:htva)[^\n]*?(-?[\d.,]+)\s*€(?!.*€)/i'
    ],

    'amount_tva' => [
        // Ligne "TVA", "T.V.A.", etc.
        '/\btva[^\n]*?(-?[\d.,]+)\s*€(?!.*€)/i',
    ],

    'amount_tvac' => [
        '/\btotal.*(?:facture)[^\n]*?(-?[\d.,]+)\s*€(?!.*€)/i',
        '/\btotal.*(?:a payer)[^\n]*?(-?[\d.,]+)\s*€(?!.*€)/i',
        '/\ba payer[^\n]*?(-?[\d.,]+)\s*€(?!.*€)/i',
    ],

    'due_date' => [
        '/\b(?:date\s+d[’\']echeance|d[’\']echeance|date\s+limite\s+de\s+paiement)\s*[:\-]?\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i',
        '/\bpaiement\s+(avant|pour)\s+le\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i',
        '/\bavant\s+le\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i',
    ],

    // ex. BE38 0015 0942 4272
    'iban' => [
        '/(BE\d{2} ?\d{4} ?\d{4} ?\d{4})/i',
    ],

    // ex. +++140/3598/57438+++
    'payment_id' => [
        '/(\+{3}\d{3}\/\d{4}\/\d{5})/',
        '/(\d{3}\/\d{4}\/\d{5}\+{3})/',
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
                $value = $normalize_number($value);
            }
            elseif(preg_match('/period_/', $field)) {
                $value = $toIsoDate($value);
            }
            elseif(preg_match('/_address/', $field)) {
                $value = $extract_address($value);
            }
            elseif($field === 'seller_vat') {
                $value = str_replace(' ', '', $value);
            }
            elseif($field === 'iban') {
                $value = str_replace(' ', '', $value);
            }
            elseif($field === 'payment_id') {
                $value = str_replace('+', '', $value);
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
