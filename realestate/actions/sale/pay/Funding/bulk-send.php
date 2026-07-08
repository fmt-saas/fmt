<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use finance\bank\CondominiumBankAccount;
use realestate\sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Checks if the Funding relates to an Ownership for which a property transfer is in progress.",
    'extends'       => 'core_model_check',
    'params'        => [
        'id' =>  [
            'type'             => 'many2one',
            'foreign_object'   => 'realestate\sale\pay\Funding',
            'description'      => 'Identifier of the Funding.',
        ],
        'ids' =>  [
            'type'             => 'one2many',
            'foreign_object'   => 'realestate\sale\pay\Funding',
            'description'      => 'List of Funding identifiers.',
            'default'          => []
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
['context' => $context] = $providers;

if(!isset($params['id']) && !isset($params['ids'])) {
    throw new Exception("missing_id_or_ids", EQ_ERROR_INVALID_PARAM);
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];

$fundings_ids = $params['ids'];

if(isset($params['id']) && $params['id']) {
    $fundings_ids[] = $params['id'];
}

// ensure booking object exists and is readable
$fundings = Funding::ids($fundings_ids)
    ->read(['name', 'condo_id', 'remaining_amount', 'has_mandate', 'is_sent', 'is_exported'])
    ->get();

if(count($fundings) <= 0) {
    throw new Exception("no_fundings", EQ_ERROR_INVALID_PARAM);
}


// #memo - by convention we create a SEPA file for each Funding (instead of grouping them)
foreach($fundings as $funding_id => $funding) {

    if($funding['is_sent']) {
        // sepa_already_sent
        continue;
    }
    if($funding['remaining_amount'] >= 0) {
        // sepa_only_for_outgoing_funding
        continue;
    }
    if($funding['has_mandate']) {
        // sepa_only_for_manual_funding
        continue;
    }

    eQual::run('do', 'sale_pay_Funding_generate-sepa', [
        'id' => $funding_id
    ]);

}

$context->httpResponse()
        ->status(201)
        ->send();
