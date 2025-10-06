<?php
/*
    Developed by Yesbabylon – https://yesbabylon.com
    (c) 2025–2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License – https://www.gnu.org/licenses/agpl-3.0.html
*/
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF file of given fund request.',
    'params'        => [
        'fiscal_period_id' => [
            'label'             => 'Fiscal Period',
            'description'       => 'Identifier of the targeted Fiscal Period.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalPeriod',
            'required'          => true
        ],

        'ownership_id' => [
            'description'       => 'Identifier of the targeted Ownership (from which Owner is deduced).',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership',
            'required'          => true
        ],

        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ],

        'view_id' => [
            'description' => 'View id of the template to use.',
            'type'        => 'string',
            'default'     => 'print.default'
        ],

        'lang' =>  [
            'description' => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'        => 'string',
            'default'     => constant('DEFAULT_LANG')
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


try {

    $html = (string) eQual::run('get', 'realestate_funding_fiscalperiod_expensestatement_single-html', ['fiscal_period_id' => $params['fiscal_period_id'], 'ownership_id' => $params['ownership_id']]);

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

    $font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
    $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / " . $page_count, $font, 9, array(0,0,0));

    // enforce odd amount of pages
    $page_count = $canvas->get_page_count();
    if($page_count % 2 !== 0) {
        $canvas->new_page();
        /*
        $blank_page_html = '<div style="page-break-before: always;">&nbsp;</div>';
        $html = str_replace('</body>', $blank_page_html . '</body>', $html);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        */
    }


    // get generated PDF raw binary
    $output = $dompdf->output();
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
