<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use automation\agent\Conversation;
use automation\agent\Message;
use automation\agent\MessageStep;

[$params, $providers] = eQual::announce([
    'type'          => 'get',
    'name'          => 'automation_agent_status',
    'package_name'  => 'automation',
    'description'   => 'Return a read-only UI projection of a complete agent conversation.',
    'params'        => [
        'conversation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'automation\agent\Conversation',
            'description'       => 'Conversation for which the current agent status is requested.',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$conversation = Conversation::id($params['conversation_id'])
    ->read(['id', 'user_id', 'status'])
    ->first();

if(!$conversation) {
    throw new Exception('conversation_not_found', EQ_ERROR_UNKNOWN_OBJECT);
}

if((int) $conversation['user_id'] !== (int) $auth->userId()) {
    throw new Exception('access_denied', EQ_ERROR_NOT_ALLOWED);
}

$registry = eQual::run('get', 'automation_agent_tools');
$tools_by_name = [];
foreach($registry['tools'] ?? [] as $tool) {
    if(is_array($tool) && is_string($tool['name'] ?? null)) {
        $tools_by_name[$tool['name']] = $tool;
    }
}

$messages = Message::search(
        [['conversation_id', '=', $conversation['id']]],
        ['sort' => ['sequence' => 'asc']]
    )
    ->read(['id', 'sequence', 'role', 'status', 'content']);

$messages_payload = [];
$latest_agent_message = null;

foreach($messages as $message) {
    $steps = MessageStep::search(
            [['message_id', '=', $message['id']]],
            ['sort' => ['sequence' => 'asc']]
        )
        ->read(['id', 'sequence', 'type', 'status', 'data']);

    $steps_payload = [];
    foreach($steps as $step) {
        $step_data = [];
        if(is_string($step['data']) && $step['data'] !== '') {
            $decoded_data = json_decode($step['data'], true);
            $step_data = (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data))
                ? $decoded_data
                : [];
        }

        $code = 'analysis';
        $labels = [
            'pending'   => 'Analyse en attente',
            'running'   => 'Analyse de votre demande',
            'completed' => 'Demande analysée',
            'failed'    => "L'analyse n'a pas pu aboutir"
        ];

        if($step['type'] === 'tool') {
            $tool_name = is_string($step_data['name'] ?? null) ? $step_data['name'] : '';
            $tool = $tools_by_name[$tool_name] ?? [];
            $ux = is_array($tool['ux'] ?? null)
                ? $tool['ux']
                : (is_array($step_data['ux'] ?? null) ? $step_data['ux'] : []);
            $code = is_string($ux['code'] ?? null) && $ux['code'] !== '' ? $ux['code'] : 'tool';

            if($step['status'] === 'completed') {
                $label = $ux['done'] ?? 'Vérification terminée';
            }
            elseif($step['status'] === 'failed') {
                $label = "La vérification n'a pas pu aboutir";
            }
            else {
                $label = $ux['running'] ?? 'Vérification en cours';
            }
        }
        else {
            $label = $labels[$step['status']] ?? 'Analyse de votre demande';
        }

        $steps_payload[] = [
            'id'        => $step['id'],
            'sequence'  => $step['sequence'],
            'type'      => $step['type'],
            'status'    => $step['status'],
            'code'      => $code,
            'label'     => $label
        ];
    }

    $message_payload = [
        'id'        => $message['id'],
        'sequence'  => $message['sequence'],
        'role'      => $message['role'],
        'status'    => $message['status'],
        'content'   => $message['content'],
        'steps'     => $steps_payload
    ];

    $messages_payload[] = $message_payload;
    if($message['role'] === 'agent') {
        $latest_agent_message = $message_payload;
    }
}

$context->httpResponse()
    ->status(200)
    ->body([
        'conversation'     => [
            'id'     => $conversation['id'],
            'status' => $conversation['status']
        ],
        'messages'         => $messages_payload,
        'conversation_id'  => $conversation['id'],
        'status'           => $conversation['status'],
        'agent_message'    => $latest_agent_message
    ])
    ->send();
