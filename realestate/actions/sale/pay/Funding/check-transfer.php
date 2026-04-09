<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use realestate\property\OwnershipTransfer;
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
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

if(!isset($params['id']) && !isset($params['ids'])) {
    throw new Exception("missing_id_or_ids", EQ_ERROR_INVALID_PARAM);
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];
$httpResponse = $context->httpResponse()->status(200);


if(isset($params['id'])) {
    $funding_ids = [ $params['id'] ];
}
else {
    $funding_ids = $params['ids'];
}

// ensure booking object exists and is readable
$fundings = Funding::ids($funding_ids)
    ->read(['name', 'ownership_id', 'issue_date']);

if(!$fundings->count()) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}


foreach($fundings as $funding) {

    if(!$funding['ownership_id']) {
        continue;
    }

    $has_ownership_transfer = false;

    $ownershipTransfer = OwnershipTransfer::search([
            ['old_ownership_id', '=', $funding['ownership_id']],
            ['confirmation_date', '<>', null],
            ['confirmation_date', '<=', $funding['issue_date']],
            ['status', '<>', 'closed']
        ])
        ->first();

    if($ownershipTransfer) {
        $has_ownership_transfer = true;
    }

    // #memo - in some cases the booking price might result to a null value (ex. price with 100% discount : this is used for internal booking for the organization itself), so we cannot test ` $booking['price'] == 0`
    if($has_ownership_transfer) {
        $links = [];

        $result[$funding['id']][] = '';

        // #todo
        // $links[] = "[{$funding['name']}](/sale/#/funding/{$funding['id']})";

        // by convention we dispatch an alert that relates to the controller itself.
        $dispatch->dispatch('sale.pay.funding.ownership_transfer', 'realestate\sale\pay\Funding', $funding['id'], 'important', 'realestate_sale_pay_Funding_check-transfer', ['id' => $funding['id']], $link);

        $httpResponse->status(qn_error_http(EQ_ERROR_MISSING_PARAM));
    }
    else {
        // symmetrical removal of the alert (if any)
        $dispatch->cancel('sale.pay.funding.ownership_transfer', 'realestate\sale\pay\Funding', $funding['id']);
    }
}

$httpResponse->body($result)
             ->send();
