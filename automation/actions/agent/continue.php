<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

require_once EQ_BASEDIR . '/packages/automation/services/agent/LlmProviderInterface.php';
require_once EQ_BASEDIR . '/packages/automation/services/agent/AgentOrchestrator.php';
require_once EQ_BASEDIR . '/packages/automation/services/agent/provider/OpenAIProvider.php';

use automation\agent\AgentOrchestrator;
use automation\agent\Conversation;
use automation\agent\Message;
use automation\agent\provider\OpenAIProvider;

[$params, $providers] = eQual::announce([
    'type'          => 'do',
    'name'          => 'automation_agent_continue',
    'package_name'  => 'automation',
    'description'   => 'Advance the pending agent message by exactly one LLM and tool orchestration round.',
    'params'        => [
        'conversation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'automation\agent\Conversation',
            'description'       => 'Conversation for which the pending agent message must be processed.',
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

$user_id = $auth->userId();

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

$agent_message = Message::search([
        ['conversation_id', '=', $conversation['id']],
        ['role', '=', 'agent'],
        ['status', '=', 'pending']
    ])
    ->read(['id'])
    ->first();

if(!$agent_message) {
    throw new Exception('no_pending_agent_message', EQ_ERROR_CONFLICT_OBJECT);
}

$recover_failed_message = static function(int $conversation_id, int $message_id): void {
    try {
        Message::id($message_id)->update(['status' => 'failed']);
    }
    catch(Throwable $recovery_error) {
        trigger_error('APP::Unable to mark the agent message as failed.', EQ_REPORT_WARNING);
    }

    try {
        Conversation::id($conversation_id)->update(['status' => 'pending']);
    }
    catch(Throwable $recovery_error) {
        trigger_error('APP::Unable to restore the conversation pending status.', EQ_REPORT_WARNING);
    }
};

$is_provider_error = static function(Throwable $throwable): bool {
    $message = $throwable->getMessage();
    $data = @unserialize($message, ['allowed_classes' => false]);

    return (is_array($data)
            && is_string($data['error'] ?? null)
            && strpos($data['error'], 'openai_') === 0)
        || strpos($message, 'openai_') === 0;
};

try {
    $provider = new OpenAIProvider(
        (string) \config\constant('OPENAI_API_KEY', ''),
        (string) \config\constant('OPENAI_MODEL', ''),
        (int) \config\constant('OPENAI_TIMEOUT', 30)
    );

    $registry = eQual::run('get', 'automation_agent_tools');
    $tools = $registry['tools'] ?? null;
    if(!is_array($tools)) {
        throw new Exception('invalid_tool_registry', EQ_ERROR_INVALID_CONFIG);
    }

    $orchestrator = new AgentOrchestrator(
        $provider,
        "You are a helpful agent. Answer clearly and concisely in the user's language. Use the available tools whenever authenticated account data is required. If a tool reports that required information is unavailable, do not guess: clearly explain that you cannot determine the indispensable information needed for a reliable answer.",
        $tools
    );

    $result = $orchestrator->run((int) $conversation['id']);
}
catch(Throwable $throwable) {
    if(in_array($throwable->getMessage(), ['conversation_already_running', 'no_pending_agent_message'], true)) {
        throw $throwable;
    }

    $recover_failed_message((int) $conversation['id'], (int) $agent_message['id']);

    if($is_provider_error($throwable)) {
        trigger_error('APP::Agent provider failed: ' . $throwable->getMessage(), EQ_REPORT_WARNING);
        throw new Exception('provider_error', EQ_ERROR_UNKNOWN);
    }

    trigger_error('APP::Agent orchestration failed: ' . $throwable->getMessage(), EQ_REPORT_WARNING);
    throw new Exception('agent_failed', EQ_ERROR_UNKNOWN);
}

$context->httpResponse()
    ->status(200)
    ->body([
        'conversation_id'     => $result['conversation_id'],
        'message_id'          => $result['message_id'],
        'conversation_status' => $result['conversation_status'],
        'message_status'      => $result['message_status'],
        'content'             => $result['content'],
        'awaiting_continuation' => $result['awaiting_llm_continuation']
    ])
    ->send();
