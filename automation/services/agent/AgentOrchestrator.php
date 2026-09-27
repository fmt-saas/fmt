<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent;

class AgentOrchestrator {

    private LlmProviderInterface $provider;
    private string $instructions;
    private array $tools;
    /** @var callable */
    private $tool_executor;

    public function __construct(
        LlmProviderInterface $provider,
        string $instructions,
        array $tools = [],
        ?callable $tool_executor = null
    ) {
        $this->provider = $provider;
        $this->instructions = $instructions;
        $this->tools = $tools;
        $this->tool_executor = $tool_executor ?? static function(string $tool_name, array $arguments): array {
            return \eQual::run('do', 'automation_agent_toolregistry_execute', [
                'tool_name' => $tool_name,
                'arguments' => $arguments
            ]);
        };
    }

    public function run(int $conversation_id): array {
        $conversation = Conversation::id($conversation_id)
            ->read(['id', 'status'])
            ->first();

        if(!$conversation) {
            throw new \Exception('unknown_conversation', EQ_ERROR_UNKNOWN_OBJECT);
        }

        if($conversation['status'] === 'running') {
            throw new \Exception('conversation_already_running', EQ_ERROR_CONFLICT_OBJECT);
        }

        if($conversation['status'] !== 'pending') {
            throw new \Exception('conversation_not_pending', EQ_ERROR_CONFLICT_OBJECT);
        }

        $agent_message = Message::search(
                [
                    ['conversation_id', '=', $conversation_id],
                    ['role', '=', 'agent'],
                    ['status', '=', 'pending']
                ],
                [
                    'sort'  => ['sequence' => 'desc'],
                    'limit' => 1
                ]
            )
            ->read(['id', 'sequence', 'status'])
            ->first();

        if(!$agent_message) {
            throw new \Exception('pending_agent_message_not_found', EQ_ERROR_CONFLICT_OBJECT);
        }

        $user_message = Message::search(
                [
                    ['conversation_id', '=', $conversation_id],
                    ['role', '=', 'user'],
                    ['status', '=', 'completed'],
                    ['sequence', '<', $agent_message['sequence']]
                ],
                [
                    'sort'  => ['sequence' => 'desc'],
                    'limit' => 1
                ]
            )
            ->read(['id', 'role', 'content'])
            ->first();

        if(!$user_message || trim((string) ($user_message['content'] ?? '')) === '') {
            throw new \Exception('completed_user_message_not_found', EQ_ERROR_CONFLICT_OBJECT);
        }

        [$input, $continuation_metadata] = $this->getRoundInput(
            $conversation_id,
            $agent_message['id'],
            $agent_message['sequence'],
            $user_message
        );
        $last_step = MessageStep::search(
                [['message_id', '=', $agent_message['id']]],
                [
                    'sort'  => ['sequence' => 'desc'],
                    'limit' => 1
                ]
            )
            ->read(['sequence'])
            ->first();

        $llm_step_id = null;
        $active_step_id = null;
        $tool_steps = [];
        $next_step_sequence = ($last_step['sequence'] ?? 0) + 1;
        $round_started = false;

        try {
            $this->claimConversation($conversation_id);
            $round_started = true;

            Message::id($agent_message['id'])->update(['status' => 'running']);

            $step = MessageStep::create([
                    'message_id' => $agent_message['id'],
                    'sequence'   => $next_step_sequence,
                    'type'       => 'llm',
                    'status'     => 'running',
                    'data'       => '{}'
                ])
                ->read(['id'])
                ->first();

            $llm_step_id = $step['id'];
            $active_step_id = $llm_step_id;

            $result = $this->provider->generate(
                $input,
                $continuation_metadata,
                $this->instructions,
                $this->tools
            );

            if(!is_array($result)) {
                throw new \Exception('invalid_llm_provider_response', EQ_ERROR_UNKNOWN);
            }

            $text = $result['text'] ?? null;
            $tool_calls = $result['tool_calls'] ?? [];
            if(!is_string($text)
                || !is_array($tool_calls)
                || (trim($text) === '' && !count($tool_calls))
                || !is_array($result['metadata'] ?? null)) {
                throw new \Exception('invalid_llm_provider_response', EQ_ERROR_UNKNOWN);
            }

            $llm_step_data = $result['metadata'];
            if(count($tool_calls)) {
                $llm_step_data['tool_calls'] = $tool_calls;
            }

            $encoded_metadata = json_encode(
                $llm_step_data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            MessageStep::id($llm_step_id)->update([
                'status' => 'completed',
                'data'   => $encoded_metadata
            ]);

            foreach($tool_calls as $tool_call) {
                if(!is_array($tool_call)
                    || !is_string($tool_call['call_id'] ?? null)
                    || !is_string($tool_call['name'] ?? null)
                    || !is_array($tool_call['arguments'] ?? null)) {
                    throw new \Exception('invalid_llm_provider_response', EQ_ERROR_UNKNOWN);
                }

                ++$next_step_sequence;
                $tool_ux = $this->getToolUx($tool_call['name']);
                $running_data = [
                    'call_id'   => $tool_call['call_id'],
                    'name'      => $tool_call['name'],
                    'arguments' => $tool_call['arguments'],
                    'ux'        => $tool_ux
                ];
                $tool_step = MessageStep::create([
                        'message_id' => $agent_message['id'],
                        'sequence'   => $next_step_sequence,
                        'type'       => 'tool',
                        'status'     => 'running',
                        'data'       => json_encode(
                            $running_data,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        )
                    ])
                    ->read(['id'])
                    ->first();

                $active_step_id = $tool_step['id'];
                $execution = call_user_func($this->tool_executor, $tool_call['name'], $tool_call['arguments']);

                $tool_step_data = [
                    'call_id'   => $tool_call['call_id'],
                    'name'      => $tool_call['name'],
                    'arguments' => $tool_call['arguments'],
                    'ux'        => $execution['ux'] ?? $tool_ux,
                    'result'    => $execution['result'] ?? null
                ];

                MessageStep::id($active_step_id)->update([
                    'status' => 'completed',
                    'data'   => json_encode(
                        $tool_step_data,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    )
                ]);

                $tool_steps[] = [
                    'step_id' => $active_step_id,
                    'status'  => 'completed',
                    'name'    => $tool_call['name'],
                    'call_id' => $tool_call['call_id'],
                    'ux'      => $execution['ux'] ?? $tool_ux
                ];
            }

            $message_status = count($tool_calls) ? 'pending' : 'completed';
            $message_values = ['status' => $message_status];
            if($message_status === 'completed') {
                $message_values['content'] = $text;
            }

            Message::id($agent_message['id'])->update($message_values);

            Conversation::id($conversation_id)->update(['status' => 'pending']);
        }
        catch(\Throwable $throwable) {
            if($round_started) {
                $this->recoverRound($conversation_id, $agent_message['id'], $active_step_id, $throwable);
            }

            throw $throwable;
        }

        return [
            'conversation_id'      => $conversation_id,
            'conversation_status'  => 'pending',
            'message_id'           => $agent_message['id'],
            'message_status'       => $message_status,
            'step_id'              => $llm_step_id,
            'step_status'          => 'completed',
            'tool_steps'           => $tool_steps,
            'awaiting_llm_continuation' => count($tool_steps) > 0,
            'content'              => $message_status === 'completed' ? $text : null
        ];
    }

    private function claimConversation(int $conversation_id): void {
        ['orm' => $orm, 'adapt' => $dap] = \eQual::inject(['orm', 'adapt']);
        $db = $orm->getDB();
        $adapter = $dap->get('sql');
        $db->setRecords(
            Conversation::getModelTable(),
            [$conversation_id],
            [
                'status'   => 'running',
                'modified' => $adapter->adaptOut(time(), 'datetime')
            ],
            [[['status', '=', 'pending']]]
        );

        if((int) $db->getAffectedRows() !== 1) {
            throw new \Exception('conversation_already_running', EQ_ERROR_CONFLICT_OBJECT);
        }

        // Keep the request-local ORM cache aligned with the atomic database claim.
        Conversation::id($conversation_id)->write(['status' => 'running']);
    }

    private function getRoundInput(
        int $conversation_id,
        int $message_id,
        int $current_sequence,
        $user_message
    ): array {
        $current_llm_step = MessageStep::search(
                [
                    ['message_id', '=', $message_id],
                    ['type', '=', 'llm'],
                    ['status', '=', 'completed']
                ],
                [
                    'sort'  => ['sequence' => 'desc'],
                    'limit' => 1
                ]
            )
            ->read(['sequence', 'data'])
            ->first();

        if($current_llm_step) {
            $metadata = $this->decodeStepData($current_llm_step['data']);
            $tool_steps = MessageStep::search(
                    [
                        ['message_id', '=', $message_id],
                        ['type', '=', 'tool'],
                        ['status', '=', 'completed'],
                        ['sequence', '>', $current_llm_step['sequence']]
                    ],
                    ['sort' => ['sequence' => 'asc']]
                )
                ->read(['data']);

            $input = [];
            foreach($tool_steps as $tool_step) {
                $tool_data = $this->decodeStepData($tool_step['data']);
                $call_id = $tool_data['call_id'] ?? null;
                if(!is_string($call_id) || trim($call_id) === '' || !array_key_exists('result', $tool_data)) {
                    throw new \Exception('invalid_tool_step_data', EQ_ERROR_UNKNOWN);
                }

                $input[] = [
                    'role'    => 'tool',
                    'call_id' => $call_id,
                    'content' => $tool_data['result']
                ];
            }

            if(!count($input)) {
                throw new \Exception('missing_tool_results', EQ_ERROR_UNKNOWN);
            }

            return [$input, $metadata];
        }

        return [
            [[
                'role'    => $user_message['role'],
                'content' => $user_message['content']
            ]],
            $this->getPreviousConversationMetadata($conversation_id, $current_sequence)
        ];
    }

    private function getPreviousConversationMetadata(int $conversation_id, int $current_sequence): ?array {
        $previous_agent_message = Message::search(
                [
                    ['conversation_id', '=', $conversation_id],
                    ['role', '=', 'agent'],
                    ['status', '=', 'completed'],
                    ['sequence', '<', $current_sequence]
                ],
                [
                    'sort'  => ['sequence' => 'desc'],
                    'limit' => 1
                ]
            )
            ->read(['id'])
            ->first();

        if(!$previous_agent_message) {
            return null;
        }

        $previous_step = MessageStep::search(
                [
                    ['message_id', '=', $previous_agent_message['id']],
                    ['type', '=', 'llm'],
                    ['status', '=', 'completed']
                ],
                [
                    'sort'  => ['sequence' => 'desc'],
                    'limit' => 1
                ]
            )
            ->read(['data'])
            ->first();

        if(!$previous_step || !is_string($previous_step['data']) || trim($previous_step['data']) === '') {
            throw new \Exception('invalid_llm_step_data', EQ_ERROR_UNKNOWN);
        }

        return $this->decodeStepData($previous_step['data']);
    }

    private function decodeStepData($data): array {
        if(!is_string($data) || trim($data) === '') {
            throw new \Exception('invalid_llm_step_data', EQ_ERROR_UNKNOWN);
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }
        catch(\JsonException $exception) {
            throw new \Exception('invalid_llm_step_data', EQ_ERROR_UNKNOWN);
        }

        if(!is_array($decoded)) {
            throw new \Exception('invalid_llm_step_data', EQ_ERROR_UNKNOWN);
        }

        return $decoded;
    }

    private function getToolUx(string $tool_name): ?array {
        foreach($this->tools as $tool) {
            if(is_array($tool) && ($tool['name'] ?? null) === $tool_name && is_array($tool['ux'] ?? null)) {
                return $tool['ux'];
            }
        }

        return null;
    }

    private function recoverRound(int $conversation_id, int $message_id, ?int $step_id, \Throwable $throwable): void {
        if($step_id !== null) {
            $error_key = $throwable instanceof LlmProviderException
                ? $throwable->getMessage()
                : 'agent_orchestration_error';
            $provider_metadata = $throwable instanceof LlmProviderException
                ? $throwable->metadata()
                : [];

            $failure_data = [
                'provider' => $this->provider->name(),
                'error'    => [
                    'key'         => $error_key,
                    'http_status' => $provider_metadata['http_status'] ?? null,
                    'request_id'  => $provider_metadata['request_id'] ?? null
                ]
            ];

            try {
                MessageStep::id($step_id)->update([
                    'status' => 'failed',
                    'data'   => json_encode($failure_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]);
            }
            catch(\Throwable $recovery_error) {
                trigger_error('APP::Unable to mark the agent message step as failed.', EQ_REPORT_WARNING);
            }
        }

        try {
            Message::id($message_id)->update(['status' => 'failed']);
        }
        catch(\Throwable $recovery_error) {
            trigger_error('APP::Unable to mark the agent message as failed.', EQ_REPORT_WARNING);
        }

        try {
            Conversation::id($conversation_id)->update(['status' => 'pending']);
        }
        catch(\Throwable $recovery_error) {
            trigger_error('APP::Unable to restore the conversation pending status.', EQ_REPORT_WARNING);
        }
    }
}
