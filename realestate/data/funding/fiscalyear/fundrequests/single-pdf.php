<?php
/*
    This file is part of FMT SaaS Software <https://github.com/fmt-saas/fmt>
    Some Rights Reserved, FMT SRL, 2025-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF file of given fund request.',
    'params'        => [
        'fiscal_year_id' => [
            'description'       => 'Identifier of the targeted Fiscal Year.',
            'type'              => 'many2one',
            'foreign_object'    => 'finance\accounting\FiscalYear',
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

    $html = (string) eQual::run('get', 'realestate_funding_fiscalyear_fundrequests_single-html', ['fiscal_year_id' => $params['fiscal_year_id'], 'ownership_id' => $params['ownership_id']]);

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

    // enforce odd amount of pages
    $page_count = $canvas->get_page_count();
    if($page_count % 2 !== 0) {
        $blank_page_html = '<div style="page-break-before: always;">&nbsp;</div>';
        $html = str_replace('</body>', $blank_page_html . '</body>', $html);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
    }

    $font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
    $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
    // $canvas->page_text(40, $canvas->get_height() - 35, "Export", $font, 9, array(0,0,0));

    // get generated PDF raw binary
    $output = $dompdf->output();
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template'.$e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
