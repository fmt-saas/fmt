<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => "Checks if all owners have been invited to the target assembly.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the invitation refers to.",
            'foreign_object'    => 'realestate\governance\Assembly',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                 $context
 * @var \equal\dispatch\Dispatcher         $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

// ensure booking object exists and is readable
$assembly = Assembly::id($params['id'])
    ->read(['status'])
    ->first(true);

if(!$assembly) {
    throw new Exception("unknown_assembly", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    This controller is a check: an empty response means that no alert was raised
*/
$result = [];

$httpResponse = $context->httpResponse()->status(200);

if($assembly['status'] !== 'sent') {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('realestate.governance.assembly.incomplete_sending', 'realestate\governance\Assembly', $params['id']);
}
else {
    $assembly = Assembly::id($params['id'])
        ->read(['id', 'name', 'condo_id', 'ownerships_ids' => ['owners_ids'], 'assembly_invitations_ids' => ['is_sent', 'owner_id']])
        ->first();

    $is_complete = true;

    foreach($assembly['ownerships_ids'] as $ownership) {
        foreach($ownership['owners_ids'] as $owner_id) {
            foreach($assembly['assembly_invitations_ids'] as $invite) {
                if($invite['owner_id'] === $owner_id) {
                    if(!$invite['is_sent']) {
                        $is_complete = false;
                        break 3;
                    }
                    break;
                }
            }
        }
    }
    if(!$is_complete) {
        $result[] = $assembly['id'];
        $httpResponse->status(eq_error_http(EQ_ERROR_MISSING_PARAM));
        // by convention we dispatch an alert that relates to the controller itself.
        $dispatch->dispatch('realestate.governance.assembly.incomplete_sending', 'realestate\governance\Assembly', $params['id'], 'important', 'realestate_governance_Assembly_check-invite', ['id' => $params['id']]);
    }
    else {
        // symmetrical removal of the alert (if any)
        $dispatch->cancel('realestate.governance.assembly.incomplete_sending', 'realestate\governance\Assembly', $params['id']);
    }
}

$httpResponse->body($result)
             ->send();
