<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use realestate\property\transfer\OwnershipTransferSettlementCorrespondence;

[$params, $providers] = eQual::announce([
    'description' => 'Render an ownership-transfer settlement correspondence as PDF.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlementCorrespondence',
            'description'    => 'Settlement correspondence to render.',
            'required'       => true
        ]
    ],
    'access'    => ['visibility' => 'protected'],
    'response'  => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

['context' => $context] = $providers;

if(!OwnershipTransferSettlementCorrespondence::id($params['id'])->first()) {
    throw new Exception('unknown_settlement_correspondence', EQ_ERROR_UNKNOWN_OBJECT);
}

try {
    $html = (string) eQual::run(
        'get',
        'realestate_property_transfer_OwnershipTransferSettlementCorrespondence_render-html',
        ['id' => $params['id']]
    );

    $options = new DompdfOptions();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();

    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont('helvetica', 'regular');
    $canvas->page_text(530, $canvas->get_height() - 35, 'p. {PAGE_NUM} / {PAGE_COUNT}', $font, 9, [0, 0, 0]);

    $output = $dompdf->output();
}
catch(Throwable $e) {
    trigger_error('APP::Unable to render ownership transfer settlement PDF: ' . $e->getMessage(), EQ_REPORT_ERROR);
    throw new Exception('settlement_correspondence_pdf_rendering_failed', EQ_ERROR_INVALID_CONFIG);
}

$context->httpResponse()
    ->header('Content-Disposition', 'inline; filename="settlement-correspondence.pdf"')
    ->body($output)
    ->send();

