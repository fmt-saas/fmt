<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use realestate\governance\AssemblyMinutesCorrespondence;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF Attendance Register for a given Assembly.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific AssemblyMinutesCorrespondence to consider.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\governance\AssemblyMinutesCorrespondence',
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

$assemblyMinutesCorrespondence = AssemblyMinutesCorrespondence::id($params['id'])
    ->first();

if(!$assemblyMinutesCorrespondence) {
    throw new Exception('unknown_assembly_invitation', EQ_ERROR_UNKNOWN_OBJECT);
}

try {

    $html = (string) eQual::run('get', 'realestate_governance_AssemblyMinutesCorrespondence_render-html', [
            'id'            => $params['id']
        ]);

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
    $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, [0,0,0]);

    // enforce odd amount of pages
    if($page_count % 2 !== 0) {
        $canvas->new_page();
    }

    // get generated PDF raw binary
    $output = $dompdf->output();
}
catch(Exception $e) {
    trigger_error('APP::Error while rendering template' . $e->getMessage(), EQ_ERROR_INVALID_CONFIG);
    throw new Exception($e->getMessage(), EQ_ERROR_INVALID_CONFIG);
}


$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
