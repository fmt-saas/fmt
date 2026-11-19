<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use fmt\setting\Setting;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a basic PDF representation of invoice data extracted from a UBL document.',
    'params'        => [
        'invoice_data' => [
            'description'   => 'Normalized invoice data returned by the UBL invoice extractor.',
            'type'          => 'array',
            'required'      => true
        ],
        'filename' => [
            'description'   => 'Name given to the generated PDF file.',
            'type'          => 'string',
            'default'       => 'invoice.pdf'
        ],
        'lang' => [
            'description'   => 'Language used for labels in the generated PDF (2-letter ISO 639-1 code).',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/pdf'
    ],

    'constants'     => ['DEFAULT_LANG'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

/**
 * Methods
 */

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

$formatDate = function($value) use($date_format) {
    if(empty($value)) {
        return '';
    }

    $timestamp = strtotime($value);
    return ($timestamp === false) ? '' : date($date_format, $timestamp);
};


/**
 * Action
 */

$invoice_data = $params['invoice_data'];

if(!in_array($invoice_data['document_type'] ?? null, ['Invoice', 'CreditNote'], true)) {
    throw new Exception('invalid_invoice_data', EQ_ERROR_INVALID_PARAM);
}

$invoice_data['issue_date'] = $formatDate($invoice_data['issue_date'] ?? null);
$invoice_data['due_date'] = $formatDate($invoice_data['due_date'] ?? null);

if(isset($invoice_data['invoice_period'])) {
    $invoice_data['invoice_period']['start_date'] = $formatDate($invoice_data['invoice_period']['start_date'] ?? null);
    $invoice_data['invoice_period']['end_date'] = $formatDate($invoice_data['invoice_period']['end_date'] ?? null);
}

$invoice_data += [
    'invoice_number' => '',
    'currency'       => 'EUR',
    'supplier'       => [],
    'customer'       => [],
    'payment'        => [],
    'lines'          => [],
    'totals'         => []
];

$invoice_data['supplier'] += ['name' => '', 'vat_id' => '', 'company_id' => '', 'address' => []];
$invoice_data['customer'] += ['name' => '', 'vat_id' => '', 'company_id' => '', 'address' => []];
$invoice_data['supplier']['address'] += ['street' => '', 'postal_code' => '', 'city' => '', 'country' => ''];
$invoice_data['customer']['address'] += ['street' => '', 'postal_code' => '', 'city' => '', 'country' => ''];
$invoice_data['payment'] += ['iban' => '', 'bic' => '', 'payment_id' => '', 'payment_means_code' => ''];
$invoice_data['totals'] += ['total_excl_tax' => 0.0, 'total_tax' => 0.0, 'total_incl_tax' => 0.0, 'payable_amount' => 0.0];

foreach($invoice_data['lines'] as &$line) {
    $line += [
        'id'          => '',
        'description' => '',
        'amount'      => 0.0,
        'unit_price'  => 0.0,
        'unit_code'   => '',
        'quantity'    => 0.0,
        'tax'         => []
    ];
    $line['tax'] += ['percent' => 0.0];
}
unset($line);

$default_labels = [
    'invoice'           => 'Invoice',
    'credit_note'       => 'Credit note',
    'supplier'          => 'Supplier',
    'customer'          => 'Customer',
    'issue_date'        => 'Issue date',
    'due_date'          => 'Due date',
    'buyer_reference'   => 'Buyer reference',
    'invoice_period'    => 'Invoice period',
    'vat_number'        => 'VAT number',
    'company_number'    => 'Company number',
    'line_number'       => '#',
    'description'       => 'Description',
    'quantity'          => 'Quantity',
    'unit_price'        => 'Unit price',
    'vat'               => 'VAT',
    'amount'            => 'Amount',
    'total_excl_tax'    => 'Total excl. tax',
    'total_tax'         => 'Tax',
    'total_incl_tax'    => 'Total incl. tax',
    'payable_amount'    => 'Amount due',
    'payment_details'   => 'Payment details',
    'iban'              => 'IBAN',
    'bic'               => 'BIC',
    'payment_reference' => 'Payment reference'
];

$labels = $default_labels;
$labels_path = EQ_BASEDIR . '/packages/purchase/i18n/' . $params['lang'] . '/accounting/invoice/ubl/PurchaseInvoice.print.default.json';
if(file_exists($labels_path)) {
    $translated_labels = json_decode(file_get_contents($labels_path), true);
    if(is_array($translated_labels)) {
        $labels = array_merge($default_labels, $translated_labels);
    }
}

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR . '/packages/purchase/views/accounting/invoice/ubl');
    $twig = new TwigEnvironment($loader, ['autoescape' => 'html']);
    $template = $twig->load('PurchaseInvoice.print.default.html');

    $html = $template->render([
        'invoice' => $invoice_data,
        'labels'  => $labels,
        'lang'    => $params['lang']
    ]);

    $options = new DompdfOptions();
    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont('helvetica', 'regular');
    $canvas->page_text(520, $canvas->get_height() - 28, '{PAGE_NUM} / {PAGE_COUNT}', $font, 8);

    $output = $dompdf->output();
}
catch(Throwable $e) {
    trigger_error('APP::Unable to render UBL invoice PDF: ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('invoice_pdf_generation_failed', EQ_ERROR_INVALID_CONFIG);
}

$filename = trim($params['filename']);
if($filename === '') {
    $filename = 'invoice.pdf';
}
elseif(strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
    $filename .= '.pdf';
}

$context
    ->httpResponse()
    ->header('Content-Disposition', 'inline; filename="' . str_replace(["\r", "\n", '"'], '', $filename) . '"')
    ->body($output, true)
    ->send();
