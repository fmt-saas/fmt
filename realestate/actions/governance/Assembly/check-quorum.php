<?php
// 6) refresh assembly valid/invalid alert

use realestate\governance\Assembly;

[$params, $providers] = eQual::announce([
    'description'   => "Check if the Assembly double-quorum is met or not.",
    'params'        => [
        'id' =>  [
            'type'              => 'many2one',
            'description'       => "The assembly the attendee must be added to.",
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

// cancel any existing alert (in case of multiple calls to this action in a short period of time, we want to avoid stacking multiple alerts of the same type)
$dispatch->cancel('realestate.workflow.assembly.quorum_not_reached', 'realestate\governance\Assembly', $params['id']);
$dispatch->cancel('realestate.workflow.assembly.quorum_reached', 'realestate\governance\Assembly', $params['id']);

// check assembly validity and dispatch corresponding alert
try {
    Assembly::id($params['id'])->assert('is_quorum_reached');
    $dispatch->dispatch('realestate.workflow.assembly.quorum_reached', 'realestate\governance\Assembly', $params['id'], 'notice');
}
catch(Exception $e) {
    $dispatch->dispatch('realestate.workflow.assembly.quorum_not_reached', 'realestate\governance\Assembly', $params['id'], 'important', 'realestate_governance_Assembly', ['id' => $params['id']]);
}

$context->httpResponse()
        ->status(204)
        ->send();
