<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/
use realestate\funding\FundRequest;

[$params, $providers] = eQual::announce([
    'description'   => 'Generate a PDF file of the given fund request.',
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the specific fund request that must be returned.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\funding\FundRequest',
            'required'          => true
        ],
        'ownership_id' => [
            'description'       => 'Optional identifier of a specific targeted Ownership to limit to rendering to.',
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\ownership\Ownership',
        ],
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

$fundRequest = FundRequest::id($params['id'])
    ->read(['condo_id' => ['ownerships_ids' => ['date_to']], 'fiscal_year_id' => ['id', 'date_to']])
    ->first();

if(!$fundRequest) {
    throw new Exception('unknown_fund_request', EQ_ERROR_UNKNOWN_OBJECT);
}

/* not implemented */

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();