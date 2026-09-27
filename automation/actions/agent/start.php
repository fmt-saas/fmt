<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use automation\agent\Conversation;
use automation\agent\Message;

[$params, $providers] = eQual::announce([
    'type'          => 'do',
    'name'          => 'automation_agent_start',
    'package_name'  => 'automation',
    'description'   => 'Start an agent round by creating the user and pending agent messages.',
    'params'        => [
        'conversation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'automation\agent\Conversation',
            'description'       => 'Existing conversation to which the round must be appended.'
        ],
        'message' => [
            'type'              => 'string',
            'usage'             => 'text/plain',
            'description'       => 'User message that starts the round.',
            'required'          => true
        ],
        'context' => [
            'type'              => 'array',
            'description'       => 'Optional JSON-compatible context replacing the conversation context.'
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

if(trim($params['message']) === '') {
    throw new Exception('empty_message', EQ_ERROR_INVALID_PARAM);
}

$encoded_context = null;
if(array_key_exists('context', $params)) {
    try {
        $encoded_context = json_encode(
            $params['context'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
    catch(JsonException $exception) {
        throw new Exception('invalid_context', EQ_ERROR_INVALID_PARAM);
    }
}

$user_id = $auth->userId();
$conversation_id = null;
$user_message_id = null;
$agent_message_id = null;
$created_conversation = false;

try {
    if(isset($params['conversation_id'])) {
        $conversation = Conversation::id($params['conversation_id'])
            ->read(['id', 'user_id', 'status'])
            ->first();

        if(!$conversation) {
            throw new Exception('conversation_not_found', EQ_ERROR_UNKNOWN_OBJECT);
        }

        if((int) $conversation['user_id'] !== (int) $user_id) {
            throw new Exception('access_denied', EQ_ERROR_NOT_ALLOWED);
        }

        if($conversation['status'] === 'running') {
            throw new Exception('conversation_already_running', EQ_ERROR_CONFLICT_OBJECT);
        }

        if($conversation['status'] !== 'pending') {
            throw new Exception('conversation_not_pending', EQ_ERROR_CONFLICT_OBJECT);
        }

        $active_agent_message = Message::search([
                ['conversation_id', '=', $conversation['id']],
                ['role', '=', 'agent'],
                ['status', 'in', ['pending', 'running']]
            ])
            ->read(['id'])
            ->first();

        if($active_agent_message) {
            throw new Exception('agent_message_already_pending', EQ_ERROR_CONFLICT_OBJECT);
        }

        $conversation_id = $conversation['id'];
    }
    else {
        $values = [
            'user_id' => $user_id
        ];

        if($encoded_context !== null) {
            $values['context'] = $encoded_context;
        }

        $conversation = Conversation::create($values)
            ->read(['id'])
            ->first();

        $conversation_id = $conversation['id'];
        $created_conversation = true;
    }

    $last_message = Message::search(
            [['conversation_id', '=', $conversation_id]],
            [
                'sort'  => ['sequence' => 'desc'],
                'limit' => 1
            ]
        )
        ->read(['sequence'])
        ->first();

    $user_sequence = ($last_message['sequence'] ?? 0) + 1;

    $user_message = Message::create([
            'conversation_id' => $conversation_id,
            'sequence'        => $user_sequence,
            'role'            => 'user',
            'status'          => 'completed',
            'content'         => $params['message']
        ])
        ->read(['id'])
        ->first();

    $user_message_id = $user_message['id'];

    $agent_message = Message::create([
            'conversation_id' => $conversation_id,
            'sequence'        => $user_sequence + 1,
            'role'            => 'agent',
            'status'          => 'pending'
        ])
        ->read(['id'])
        ->first();

    $agent_message_id = $agent_message['id'];

    if(!$created_conversation && $encoded_context !== null) {
        Conversation::id($conversation_id)->update(['context' => $encoded_context]);
    }
}
catch(Throwable $throwable) {
    if($created_conversation && $conversation_id !== null) {
        Conversation::id($conversation_id)->delete(true);
    }
    else {
        if($agent_message_id !== null) {
            Message::id($agent_message_id)->delete(true);
        }
        if($user_message_id !== null) {
            Message::id($user_message_id)->delete(true);
        }
    }

    throw $throwable;
}

$context->httpResponse()
    ->status(201)
    ->body([
        'conversation_id'   => $conversation_id,
        'user_message_id'   => $user_message_id,
        'agent_message_id'  => $agent_message_id,
        'message_id'        => $agent_message_id,
        'status'            => 'pending',
        'conversation_status' => 'pending',
        'message_status'    => 'pending'
    ])
    ->send();
