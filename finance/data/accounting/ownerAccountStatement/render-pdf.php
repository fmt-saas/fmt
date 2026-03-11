<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;

[$params, $providers] = eQual::announce([
    'description'   => 'Renders the owner account statement as a PDF document for a given ownership and date range.',
    'params'        => [
        'ownership_id' => [
            'type'              => 'many2one',
            'description'       => "The ownership that the owner refers to.",
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "First date of the time interval.",
            'required'          => true
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility' => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers'     => ['context'],
    'constants'     => ['L10N_TIMEZONE', 'L10N_LOCALE']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$html = eQual::run('get', 'finance_accounting_ownerAccountStatement_render-html', $params);

try {
    /*
        Convert HTML to PDF
    */

    // instantiate and use the dompdf class
    $options = new DompdfOptions();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();
    $canvas = $dompdf->getCanvas();

    $page_count = $canvas->get_page_count();

    $font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
    $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / " . $page_count, $font, 9, [0,0,0]);

    // enforce odd amount of pages
    if($page_count % 2 !== 0) {
        $canvas->new_page();
    }

    // get generated PDF raw binary
    $output = $dompdf->output();
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
