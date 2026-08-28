<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\property\transfer\OwnershipTransferSettlement;
use realestate\property\transfer\OwnershipTransferSettlementCorrespondence;

[$params, $providers] = eQual::announce([
    'description' => 'Generate missing documents and queue all settlement correspondence emails.',
    'params'      => [
        'id' => [
            'type'           => 'many2one',
            'foreign_object' => 'realestate\property\transfer\OwnershipTransferSettlement',
            'description'    => 'Settlement whose correspondence emails must be queued.',
            'required'       => true
        ]
    ],
    'access'    => ['visibility' => 'protected'],
    'response'  => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers' => ['context']
]);

['context' => $context] = $providers;

if(!OwnershipTransferSettlement::id($params['id'])->first()) {
    throw new Exception('unknown_ownership_transfer_settlement', EQ_ERROR_UNKNOWN_OBJECT);
}

$correspondences = OwnershipTransferSettlementCorrespondence::search([
        ['settlement_id', '=', $params['id']],
        ['communication_method', '=', 'email']
    ])
    ->read(['document_id', 'is_sent']);

foreach($correspondences as $correspondence_id => $correspondence) {
    if($correspondence['is_sent']) {
        continue;
    }

    try {
        if(!$correspondence['document_id']) {
            eQual::run(
                'do',
                'realestate_property_transfer_OwnershipTransferSettlementCorrespondence_generate-document',
                ['id' => $correspondence_id]
            );
        }

        eQual::run(
            'do',
            'realestate_property_transfer_OwnershipTransferSettlementCorrespondence_send-email',
            ['id' => $correspondence_id]
        );
    }
    catch(Throwable $e) {
        trigger_error('APP::Unable to send settlement correspondence email: ' . $e->getMessage(), EQ_REPORT_ERROR);
    }
}

$context->httpResponse()
    ->status(204)
    ->send();

