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
use automation\agent\LlmProviderInterface;
use automation\agent\Message;
use automation\agent\MessageStep;
use automation\agent\provider\OpenAIProvider;
use core\User;

$fixture_conversation_ids = [];
$test_providers = eQual::inject(['auth']);
$test_auth = $test_providers['auth'];

$create_conversation_fixture = function(string $marker, string $question, string $status = 'pending') use(&$fixture_conversation_ids, $test_auth): int {
    $user = User::id($test_auth->userId())
        ->read(['id'])
        ->first();

    if(!$user) {
        throw new Exception('missing_test_user', EQ_ERROR_UNKNOWN_OBJECT);
    }

    $conversation = Conversation::create([
            'user_id' => $user['id'],
            'status'  => $status,
            'context' => json_encode(['test' => $marker])
        ])
        ->read(['id'])
        ->first();

    $fixture_conversation_ids[] = $conversation['id'];

    Message::create([
        'conversation_id' => $conversation['id'],
        'sequence'        => 1,
        'role'            => 'user',
        'status'          => 'completed',
        'content'         => $question
    ]);

    Message::create([
        'conversation_id' => $conversation['id'],
        'sequence'        => 2,
        'role'            => 'agent',
        'status'          => 'pending'
    ]);

    return (int) $conversation['id'];
};

$cleanup_conversations = function() use(&$fixture_conversation_ids): void {
    if(count($fixture_conversation_ids)) {
        Conversation::ids(array_values(array_unique($fixture_conversation_ids)))->delete(true);
        $fixture_conversation_ids = [];
    }
};

$tests = [
    '0101' => [
        'description' => 'Build and parse an OpenAI Responses request.',
        'act' => function() {
            $captured_payload = null;
            $transport = function($method, $url, $headers, $body, $connect_timeout, $timeout) use(&$captured_payload) {
                $captured_payload = json_decode($body, true);

                return [
                    'status'  => 200,
                    'headers' => ['X-Request-Id' => 'request_test_1'],
                    'body'    => json_encode([
                        'id'     => 'resp_test_1',
                        'status' => 'completed',
                        'model'  => 'test-model',
                        'output' => [
                            [
                                'type'    => 'message',
                                'content' => [
                                    ['type' => 'output_text', 'text' => 'First part.'],
                                    ['type' => 'output_text', 'text' => 'Second part.']
                                ]
                            ]
                        ],
                        'usage' => [
                            'input_tokens'  => 4,
                            'output_tokens' => 5,
                            'total_tokens'  => 9
                        ]
                    ])
                ];
            };

            $provider = new OpenAIProvider('test-key', 'test-model', 30, $transport);
            $result = $provider->generate(
                [
                    ['role' => 'user', 'content' => 'Question'],
                    ['role' => 'agent', 'content' => 'Previous answer']
                ],
                null,
                'Test instructions.'
            );

            return [
                'payload' => $captured_payload,
                'result'  => $result
            ];
        },
        'assert' => function($result) {
            return $result['payload']['model'] === 'test-model'
                && $result['payload']['instructions'] === 'Test instructions.'
                && $result['payload']['store'] === true
                && !isset($result['payload']['tools'])
                && !isset($result['payload']['previous_response_id'])
                && $result['payload']['input'][0]['role'] === 'user'
                && $result['payload']['input'][1]['role'] === 'assistant'
                && $result['result']['text'] === "First part.\nSecond part."
                && $result['result']['metadata']['response_id'] === 'resp_test_1'
                && $result['result']['metadata']['request_id'] === 'request_test_1'
                && $result['result']['metadata']['usage']['total_tokens'] === 9;
        }
    ],
    '0102' => [
        'description' => 'Forward previous OpenAI response metadata.',
        'act' => function() {
            $captured_payload = null;
            $transport = function($method, $url, $headers, $body) use(&$captured_payload) {
                $captured_payload = json_decode($body, true);

                return [
                    'status'  => 200,
                    'headers' => [],
                    'body'    => json_encode([
                        'id'     => 'resp_test_2',
                        'status' => 'completed',
                        'model'  => 'test-model',
                        'output' => [[
                            'type'    => 'message',
                            'content' => [['type' => 'output_text', 'text' => 'Continued answer.']]
                        ]]
                    ])
                ];
            };

            $provider = new OpenAIProvider('test-key', 'test-model', 30, $transport);
            $result = $provider->generate(
                [['role' => 'user', 'content' => 'Follow-up question']],
                ['provider' => 'openai', 'response_id' => 'resp_test_1'],
                'Test instructions.'
            );

            return [
                'payload' => $captured_payload,
                'result'  => $result
            ];
        },
        'assert' => function($result) {
            return $result['payload']['previous_response_id'] === 'resp_test_1'
                && $result['payload']['instructions'] === 'Test instructions.'
                && $result['result']['metadata']['previous_response_id'] === 'resp_test_1';
        }
    ],
    '0103' => [
        'description' => 'Normalize OpenAI provider failures.',
        'act' => function() {
            $cases = [
                'openai_timeout' => ['error' => 'timeout'],
                'openai_transport_error' => ['error' => 'transport'],
                'openai_http_error' => ['status' => 429, 'headers' => ['x-request-id' => 'request_error'], 'body' => '{}'],
                'openai_invalid_response' => ['status' => 200, 'headers' => [], 'body' => '{invalid'],
                'openai_empty_response' => [
                    'status'  => 200,
                    'headers' => [],
                    'body'    => json_encode([
                        'id'     => 'resp_empty',
                        'status' => 'completed',
                        'output' => []
                    ])
                ]
            ];
            $result = [];

            foreach($cases as $expected_error => $transport_response) {
                $provider = new OpenAIProvider(
                    'test-key',
                    'test-model',
                    30,
                    fn() => $transport_response
                );

                try {
                    $provider->generate(
                        [['role' => 'user', 'content' => 'Question']],
                        null,
                        'Test instructions.'
                    );
                    $result[$expected_error] = null;
                }
                catch(Throwable $throwable) {
                    $result[$expected_error] = [
                        'code'    => $throwable->getCode(),
                        'message' => $throwable->getMessage(),
                        'data'    => @unserialize($throwable->getMessage(), ['allowed_classes' => false])
                    ];
                }
            }

            return $result;
        },
        'assert' => function($result) {
            foreach($result as $expected_error => $error) {
                $error_key = is_array($error['data'] ?? null)
                    ? ($error['data']['error'] ?? null)
                    : ($error['message'] ?? null);

                if(!is_array($error)
                    || $error['code'] !== EQ_ERROR_UNKNOWN
                    || $error_key !== $expected_error) {
                    return false;
                }
            }

            return $result['openai_http_error']['data']['http_status'] === 429
                && $result['openai_http_error']['data']['request_id'] === 'request_error';
        }
    ],
    '0104' => [
        'description' => 'Convert generic tools and parse an OpenAI function call.',
        'act' => function() {
            $captured_payload = null;
            $transport = function($method, $url, $headers, $body) use(&$captured_payload) {
                $captured_payload = json_decode($body, true);

                return [
                    'status'  => 200,
                    'headers' => ['x-request-id' => 'request_tool_1'],
                    'body'    => json_encode([
                        'id'     => 'resp_tool_1',
                        'status' => 'completed',
                        'model'  => 'test-model',
                        'output' => [[
                            'type'      => 'function_call',
                            'call_id'   => 'call_userinfo_1',
                            'name'      => 'get_userinfo',
                            'arguments' => '{}'
                        ]]
                    ])
                ];
            };

            $tools = [
                [
                    'name'        => 'get_userinfo',
                    'description' => 'Identify the authenticated user.',
                    'parameters'  => [
                        'type'                 => 'object',
                        'properties'           => [],
                        'required'             => [],
                        'additionalProperties' => false
                    ],
                    'handler' => 'automation_agent_tools_get-userinfo',
                    'ux'      => [
                        'code'    => 'identify_owner_context',
                        'running' => "J'identifie votre dossier",
                        'done'    => 'Dossier identifié'
                    ]
                ],
                [
                    'name'        => 'get_fundings',
                    'description' => 'Return fundings for an ownership.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'ownership_id'   => ['type' => 'integer'],
                            'fiscal_year_id' => ['type' => 'integer']
                        ],
                        'required'             => ['ownership_id'],
                        'additionalProperties' => false
                    ],
                    'handler' => 'automation_agent_tools_get-fundings',
                    'ux'      => []
                ]
            ];

            $provider = new OpenAIProvider('test-key', 'test-model', 30, $transport);
            $result = $provider->generate(
                [['role' => 'user', 'content' => 'Quel est mon dossier ?']],
                null,
                'Test instructions.',
                $tools
            );

            return [
                'payload' => $captured_payload,
                'result'  => $result
            ];
        },
        'assert' => function($result) {
            $tool = $result['payload']['tools'][0] ?? [];
            $tool_with_optional_parameter = $result['payload']['tools'][1] ?? [];
            $call = $result['result']['tool_calls'][0] ?? [];

            return ($tool['type'] ?? null) === 'function'
                && ($tool['name'] ?? null) === 'get_userinfo'
                && ($tool['strict'] ?? null) === true
                && ($tool_with_optional_parameter['strict'] ?? null) === false
                && ($tool['parameters']['properties'] ?? null) === []
                && ($result['payload']['tool_choice'] ?? null) === 'auto'
                && ($result['payload']['parallel_tool_calls'] ?? null) === false
                && !array_key_exists('handler', $tool)
                && !array_key_exists('ux', $tool)
                && ($result['result']['text'] ?? null) === ''
                && ($call['call_id'] ?? null) === 'call_userinfo_1'
                && ($call['name'] ?? null) === 'get_userinfo'
                && ($call['arguments'] ?? null) === [];
        }
    ],
    '0105' => [
        'description' => 'Expose a complete provider-independent tool registry.',
        'act' => function() {
            $registry = eQual::run('get', 'automation_agent_tools');

            try {
                eQual::run('do', 'automation_agent_toolregistry_execute', [
                    'tool_name' => 'unknown_tool',
                    'arguments' => []
                ]);
                $unknown_tool_error = null;
            }
            catch(Exception $exception) {
                $unknown_tool_error = $exception->getMessage();
            }

            try {
                eQual::run('do', 'automation_agent_toolregistry_execute', [
                    'tool_name' => 'get_userinfo',
                    'arguments' => ['ownership_id' => 1]
                ]);
                $invalid_arguments_error = null;
            }
            catch(Exception $exception) {
                $invalid_arguments_error = $exception->getMessage();
            }

            return [
                'registry'                => $registry,
                'unknown_tool_error'      => $unknown_tool_error,
                'invalid_arguments_error' => $invalid_arguments_error
            ];
        },
        'assert' => function($result) {
            $tools = $result['registry']['tools'] ?? [];
            $tools_by_name = [];
            foreach($tools as $tool) {
                $tools_by_name[$tool['name'] ?? ''] = $tool;
            }

            $userinfo = $tools_by_name['get_userinfo'] ?? [];

            return count($tools) === 6
                && isset($tools_by_name['get_account_summary'])
                && isset($tools_by_name['get_fundings'])
                && isset($tools_by_name['get_payments'])
                && isset($tools_by_name['get_expense_statement'])
                && isset($tools_by_name['get_account_history'])
                && ($userinfo['handler'] ?? null) === 'automation_agent_tools_get-userinfo'
                && ($userinfo['ux']['running'] ?? null) === "J'identifie votre dossier"
                && ($userinfo['ux']['done'] ?? null) === 'Dossier identifié'
                && $result['unknown_tool_error'] === 'unknown_tool'
                && $result['invalid_arguments_error'] === 'invalid_tool_arguments';
        }
    ],
    '0106' => [
        'description' => 'Map provider-independent tool results to OpenAI function call outputs.',
        'act' => function() {
            $captured_payload = null;
            $transport = function($method, $url, $headers, $body) use(&$captured_payload) {
                $captured_payload = json_decode($body, true);

                return [
                    'status'  => 200,
                    'headers' => [],
                    'body'    => json_encode([
                        'id'     => 'resp_tool_2',
                        'status' => 'completed',
                        'model'  => 'test-model',
                        'output' => [[
                            'type'    => 'message',
                            'content' => [['type' => 'output_text', 'text' => 'Tool result received.']]
                        ]]
                    ])
                ];
            };

            $provider = new OpenAIProvider('test-key', 'test-model', 30, $transport);
            $provider->generate(
                [[
                    'role'    => 'tool',
                    'call_id' => 'call_account_1',
                    'content' => ['balance' => 1240]
                ]],
                ['provider' => 'openai', 'response_id' => 'resp_tool_1'],
                'Test instructions.'
            );

            return $captured_payload;
        },
        'assert' => function($result) {
            $input = $result['input'][0] ?? [];

            return ($result['previous_response_id'] ?? null) === 'resp_tool_1'
                && ($input['type'] ?? null) === 'function_call_output'
                && ($input['call_id'] ?? null) === 'call_account_1'
                && json_decode($input['output'] ?? '', true) === ['balance' => 1240];
        }
    ],
    '0201' => [
        'description' => 'Keep continuity across two orchestrated rounds.',
        'arrange' => function() use($create_conversation_fixture) {
            return $create_conversation_fixture(
                'automation-agent-provider-success',
                'Que signifie un solde débiteur ?'
            );
        },
        'act' => function($conversation_id) {
            $provider = new class implements LlmProviderInterface {
                public array $continuations = [];

                public function name(): string {
                    return 'fake';
                }

                public function generate(array $messages, ?array $continuation_metadata, string $instructions, array $tools = []): array {
                    $previous_response_id = $continuation_metadata['response_id'] ?? null;
                    $this->continuations[] = $previous_response_id;
                    $response_id = 'resp_fake_' . count($this->continuations);

                    return [
                        'text'     => 'Answer ' . count($this->continuations),
                        'metadata' => [
                            'provider'             => 'fake',
                            'response_id'          => $response_id,
                            'previous_response_id' => $previous_response_id
                        ]
                    ];
                }
            };

            $orchestrator = new AgentOrchestrator($provider, 'Test instructions.');
            $first_result = $orchestrator->run($conversation_id);

            Message::create([
                'conversation_id' => $conversation_id,
                'sequence'        => 3,
                'role'            => 'user',
                'status'          => 'completed',
                'content'         => 'Et un solde créditeur ?'
            ]);
            Message::create([
                'conversation_id' => $conversation_id,
                'sequence'        => 4,
                'role'            => 'agent',
                'status'          => 'pending'
            ]);

            $second_result = $orchestrator->run($conversation_id);
            $second_step = MessageStep::id($second_result['step_id'])
                ->read(['status', 'data'])
                ->first();
            $conversation = Conversation::id($conversation_id)
                ->read(['status'])
                ->first();
            $modified_before = [
                'conversation' => Conversation::id($conversation_id)->read(['modified'])->first()['modified'],
                'message'      => Message::id($second_result['message_id'])->read(['modified'])->first()['modified'],
                'step'         => MessageStep::id($second_result['step_id'])->read(['modified'])->first()['modified']
            ];
            $status = eQual::run('get', 'automation_agent_status', ['conversation_id' => $conversation_id]);
            eQual::run('get', 'automation_agent_status', ['conversation_id' => $conversation_id]);
            $modified_after = [
                'conversation' => Conversation::id($conversation_id)->read(['modified'])->first()['modified'],
                'message'      => Message::id($second_result['message_id'])->read(['modified'])->first()['modified'],
                'step'         => MessageStep::id($second_result['step_id'])->read(['modified'])->first()['modified']
            ];

            return [
                'conversation_id' => $conversation_id,
                'first_result'    => $first_result,
                'second_result'   => $second_result,
                'continuations'   => $provider->continuations,
                'second_step'     => $second_step,
                'conversation'    => $conversation,
                'status'          => $status,
                'modified_before' => $modified_before,
                'modified_after'  => $modified_after
            ];
        },
        'assert' => function($result) {
            $second_metadata = json_decode($result['second_step']['data'], true);
            $status_step = $result['status']['agent_message']['steps'][0] ?? [];

            return $result['first_result']['step_status'] === 'completed'
                && $result['second_result']['step_status'] === 'completed'
                && $result['continuations'] === [null, 'resp_fake_1']
                && $result['conversation']['status'] === 'pending'
                && $result['second_step']['status'] === 'completed'
                && $second_metadata['response_id'] === 'resp_fake_2'
                && $second_metadata['previous_response_id'] === 'resp_fake_1'
                && $result['status']['agent_message']['content'] === 'Answer 2'
                && ($status_step['type'] ?? null) === 'llm'
                && ($status_step['code'] ?? null) === 'analysis'
                && ($status_step['label'] ?? null) === 'Demande analysée'
                && !array_key_exists('data', $status_step)
                && count($result['status']['messages'] ?? []) === 4
                && $result['modified_before'] === $result['modified_after'];
        },
        'rollback' => function($result) use($cleanup_conversations) {
            $cleanup_conversations();
        }
    ],
    '0202' => [
        'description' => 'Recover persisted state after an LLM failure.',
        'arrange' => function() use($create_conversation_fixture) {
            return $create_conversation_fixture(
                'automation-agent-provider-failure',
                'Trigger a timeout.'
            );
        },
        'act' => function($conversation_id) {
            $provider = new class implements LlmProviderInterface {
                public function name(): string {
                    return 'fake';
                }

                public function generate(array $messages, ?array $continuation_metadata, string $instructions, array $tools = []): array {
                    throw new Exception(
                        serialize([
                            'error'      => 'fake_timeout',
                            'request_id' => 'request_timeout'
                        ]),
                        EQ_ERROR_UNKNOWN
                    );
                }
            };

            try {
                (new AgentOrchestrator($provider, 'Test instructions.'))->run($conversation_id);
            }
            catch(Throwable $throwable) {
                $agent_message = Message::search([
                        ['conversation_id', '=', $conversation_id],
                        ['role', '=', 'agent']
                    ])
                    ->read(['id', 'status'])
                    ->first();
                $step = MessageStep::search(['message_id', '=', $agent_message['id']])
                    ->read(['status', 'data'])
                    ->first();
                $conversation = Conversation::id($conversation_id)
                    ->read(['status'])
                    ->first();

                return [
                    'conversation_id' => $conversation_id,
                    'error'           => @unserialize($throwable->getMessage(), ['allowed_classes' => false]),
                    'conversation'    => $conversation,
                    'agent_message'   => $agent_message,
                    'step'            => $step
                ];
            }

            return ['conversation_id' => $conversation_id];
        },
        'assert' => function($result) {
            $data = json_decode($result['step']['data'] ?? '', true);

            return ($result['error']['error'] ?? null) === 'fake_timeout'
                && ($result['conversation']['status'] ?? null) === 'pending'
                && ($result['agent_message']['status'] ?? null) === 'failed'
                && ($result['step']['status'] ?? null) === 'failed'
                && ($data['error']['key'] ?? null) === 'fake_timeout'
                && ($data['error']['request_id'] ?? null) === 'request_timeout';
        },
        'rollback' => function($result) use($cleanup_conversations) {
            $cleanup_conversations();
        }
    ],
    '0203' => [
        'description' => 'Reject an already running conversation.',
        'arrange' => function() use($create_conversation_fixture) {
            return $create_conversation_fixture(
                'automation-agent-provider-running',
                'Question',
                'running'
            );
        },
        'act' => function($conversation_id) {
            $provider = new class implements LlmProviderInterface {
                public function name(): string {
                    return 'fake';
                }

                public function generate(array $messages, ?array $continuation_metadata, string $instructions, array $tools = []): array {
                    throw new Exception('provider_must_not_be_called');
                }
            };

            try {
                (new AgentOrchestrator($provider, 'Test instructions.'))->run($conversation_id);
            }
            catch(Exception $exception) {
                $agent_message = Message::search([
                        ['conversation_id', '=', $conversation_id],
                        ['role', '=', 'agent']
                    ])
                    ->read(['id'])
                    ->first();

                return [
                    'conversation_id' => $conversation_id,
                    'error'           => $exception->getMessage(),
                    'step_count'      => count(MessageStep::search(['message_id', '=', $agent_message['id']]))
                ];
            }

            return ['conversation_id' => $conversation_id];
        },
        'assert' => function($result) {
            return ($result['error'] ?? null) === 'conversation_already_running'
                && ($result['step_count'] ?? null) === 0;
        },
        'rollback' => function($result) use($cleanup_conversations) {
            $cleanup_conversations();
        }
    ],
    '0204' => [
        'description' => 'Complete a tool-assisted answer in two rounds and expose only UX step metadata.',
        'arrange' => function() use($create_conversation_fixture) {
            return $create_conversation_fixture(
                'automation-agent-tool-rounds',
                'Pourquoi dois-je encore payer 1240 EUR ?'
            );
        },
        'act' => function($conversation_id) {
            $provider = new class implements LlmProviderInterface {
                public array $inputs = [];

                public function name(): string {
                    return 'fake';
                }

                public function generate(array $messages, ?array $continuation_metadata, string $instructions, array $tools = []): array {
                    $this->inputs[] = [
                        'messages' => $messages,
                        'metadata' => $continuation_metadata
                    ];

                    if(count($this->inputs) === 1) {
                        return [
                            'text'       => '',
                            'tool_calls' => [[
                                'call_id'   => 'call_account_1',
                                'name'      => 'get_account_summary',
                                'arguments' => ['ownership_id' => 42]
                            ]],
                            'metadata'   => [
                                'provider'    => 'fake',
                                'response_id' => 'resp_tool_1'
                            ]
                        ];
                    }

                    return [
                        'text'       => 'Le solde restant est de 1 240 EUR.',
                        'tool_calls' => [],
                        'metadata'   => [
                            'provider'             => 'fake',
                            'response_id'          => 'resp_tool_2',
                            'previous_response_id' => $continuation_metadata['response_id'] ?? null
                        ]
                    ];
                }
            };

            $tools = [[
                'name' => 'get_account_summary',
                'ux'   => [
                    'code'    => 'load_account_summary',
                    'running' => 'Je consulte le solde de votre compte',
                    'done'    => 'Solde du compte consulté'
                ]
            ]];
            $during_status = null;
            $tool_executor = function(string $tool_name, array $arguments) use($conversation_id, &$during_status): array {
                $during_status = eQual::run('get', 'automation_agent_status', [
                    'conversation_id' => $conversation_id
                ]);

                return [
                    'ux' => [
                        'code'    => 'load_account_summary',
                        'running' => 'Je consulte le solde de votre compte',
                        'done'    => 'Solde du compte consulté'
                    ],
                    'result' => ['balance' => 1240, 'currency' => 'EUR']
                ];
            };

            $orchestrator = new AgentOrchestrator($provider, 'Test instructions.', $tools, $tool_executor);
            $first_result = $orchestrator->run($conversation_id);
            $pending_status = eQual::run('get', 'automation_agent_status', [
                'conversation_id' => $conversation_id
            ]);
            $second_result = $orchestrator->run($conversation_id);
            $completed_status = eQual::run('get', 'automation_agent_status', [
                'conversation_id' => $conversation_id
            ]);

            return [
                'conversation_id' => $conversation_id,
                'provider_inputs' => $provider->inputs,
                'first_result'    => $first_result,
                'second_result'   => $second_result,
                'during_status'   => $during_status,
                'pending_status'  => $pending_status,
                'completed_status'=> $completed_status
            ];
        },
        'assert' => function($result) {
            $during_agent = $result['during_status']['agent_message'] ?? [];
            $during_tool = $during_agent['steps'][1] ?? [];
            $pending_agent = $result['pending_status']['agent_message'] ?? [];
            $completed_agent = $result['completed_status']['agent_message'] ?? [];
            $completed_steps = $completed_agent['steps'] ?? [];
            $second_input = $result['provider_inputs'][1] ?? [];
            $tool_input = $second_input['messages'][0] ?? [];

            return ($result['first_result']['message_status'] ?? null) === 'pending'
                && ($result['first_result']['awaiting_llm_continuation'] ?? null) === true
                && ($during_agent['status'] ?? null) === 'running'
                && ($result['during_status']['conversation']['status'] ?? null) === 'running'
                && ($during_tool['status'] ?? null) === 'running'
                && ($during_tool['code'] ?? null) === 'load_account_summary'
                && ($during_tool['label'] ?? null) === 'Je consulte le solde de votre compte'
                && !array_key_exists('data', $during_tool)
                && ($pending_agent['status'] ?? null) === 'pending'
                && ($pending_agent['content'] ?? null) === null
                && ($second_input['metadata']['response_id'] ?? null) === 'resp_tool_1'
                && ($tool_input['role'] ?? null) === 'tool'
                && ($tool_input['call_id'] ?? null) === 'call_account_1'
                && ($tool_input['content']['balance'] ?? null) === 1240
                && ($result['second_result']['message_status'] ?? null) === 'completed'
                && ($result['second_result']['content'] ?? null) === 'Le solde restant est de 1 240 EUR.'
                && ($completed_agent['content'] ?? null) === 'Le solde restant est de 1 240 EUR.'
                && count($completed_steps) === 3
                && ($completed_steps[1]['label'] ?? null) === 'Solde du compte consulté'
                && ($completed_steps[2]['type'] ?? null) === 'llm'
                && !array_key_exists('data', $completed_steps[2]);
        },
        'rollback' => function($result) use($cleanup_conversations) {
            $cleanup_conversations();
        }
    ],
    '0205' => [
        'description' => 'Let the LLM explain that required information is unavailable after a tool failure.',
        'arrange' => function() use($create_conversation_fixture) {
            return $create_conversation_fixture(
                'automation-agent-tool-failure',
                'Quel est le solde de mon compte ?'
            );
        },
        'act' => function($conversation_id) {
            $provider = new class implements LlmProviderInterface {
                public array $inputs = [];

                public function name(): string {
                    return 'fake';
                }

                public function generate(array $messages, ?array $continuation_metadata, string $instructions, array $tools = []): array {
                    $this->inputs[] = [
                        'messages' => $messages,
                        'metadata' => $continuation_metadata
                    ];

                    if(count($this->inputs) === 1) {
                        return [
                            'text'       => '',
                            'tool_calls' => [[
                                'call_id'   => 'call_userinfo_failure',
                                'name'      => 'get_userinfo',
                                'arguments' => []
                            ]],
                            'metadata'   => [
                                'provider'    => 'fake',
                                'response_id' => 'resp_tool_failure_1'
                            ]
                        ];
                    }

                    return [
                        'text'       => "Je ne parviens pas à identifier votre dossier, une information indispensable pour déterminer votre solde.",
                        'tool_calls' => [],
                        'metadata'   => [
                            'provider'             => 'fake',
                            'response_id'          => 'resp_tool_failure_2',
                            'previous_response_id' => $continuation_metadata['response_id'] ?? null
                        ]
                    ];
                }
            };

            $tools = [[
                'name' => 'get_userinfo',
                'ux'   => [
                    'code'    => 'identify_owner_context',
                    'running' => "J'identifie votre dossier",
                    'done'    => 'Dossier identifié'
                ]
            ]];
            $tool_executor = function() {
                throw new Exception('missing_user_identity', EQ_ERROR_INVALID_CONFIG);
            };

            $orchestrator = new AgentOrchestrator($provider, 'Test instructions.', $tools, $tool_executor);
            $first_result = $orchestrator->run($conversation_id);
            $failed_step = MessageStep::search(
                    [
                        ['message_id', '=', $first_result['message_id']],
                        ['type', '=', 'tool']
                    ],
                    [
                        'sort'  => ['sequence' => 'desc'],
                        'limit' => 1
                    ]
                )
                ->read(['status', 'data'])
                ->first();
            $pending_status = eQual::run('get', 'automation_agent_status', [
                'conversation_id' => $conversation_id
            ]);

            $second_result = $orchestrator->run($conversation_id);
            $completed_status = eQual::run('get', 'automation_agent_status', [
                'conversation_id' => $conversation_id
            ]);

            return [
                'conversation_id' => $conversation_id,
                'provider_inputs' => $provider->inputs,
                'first_result'    => $first_result,
                'failed_step'     => $failed_step,
                'pending_status'  => $pending_status,
                'second_result'   => $second_result,
                'completed_status'=> $completed_status
            ];
        },
        'assert' => function($result) {
            $failed_data = json_decode($result['failed_step']['data'] ?? '', true);
            $failed_status_step = $result['pending_status']['agent_message']['steps'][1] ?? [];
            $second_input = $result['provider_inputs'][1]['messages'][0] ?? [];
            $final_message = $result['completed_status']['agent_message'] ?? [];

            return ($result['first_result']['message_status'] ?? null) === 'pending'
                && ($result['first_result']['awaiting_llm_continuation'] ?? null) === true
                && ($result['first_result']['tool_steps'][0]['status'] ?? null) === 'failed'
                && ($result['failed_step']['status'] ?? null) === 'failed'
                && ($failed_data['result']['success'] ?? null) === false
                && ($failed_data['result']['error']['code'] ?? null) === 'required_information_unavailable'
                && ($failed_status_step['status'] ?? null) === 'failed'
                && ($failed_status_step['label'] ?? null) === "La vérification n'a pas pu aboutir"
                && ($second_input['role'] ?? null) === 'tool'
                && ($second_input['call_id'] ?? null) === 'call_userinfo_failure'
                && ($second_input['content']['error']['code'] ?? null) === 'required_information_unavailable'
                && ($result['second_result']['message_status'] ?? null) === 'completed'
                && ($final_message['status'] ?? null) === 'completed'
                && ($final_message['content'] ?? null) === "Je ne parviens pas à identifier votre dossier, une information indispensable pour déterminer votre solde.";
        },
        'rollback' => function($result) use($cleanup_conversations) {
            $cleanup_conversations();
        }
    ],
    '0301' => [
        'description' => 'Create a conversation through start and read its complete initial status.',
        'act' => function() use(&$fixture_conversation_ids) {
            $start = eQual::run('do', 'automation_agent_start', [
                'message' => 'Pourquoi dois-je encore payer 1240 EUR ?',
                'context' => ['condo_id' => 42]
            ]);
            $fixture_conversation_ids[] = $start['conversation_id'];
            $status = eQual::run('get', 'automation_agent_status', [
                'conversation_id' => $start['conversation_id']
            ]);

            return [
                'start'  => $start,
                'status' => $status
            ];
        },
        'assert' => function($result) {
            $start = $result['start'] ?? [];
            $messages = $result['status']['messages'] ?? [];

            return ($start['conversation_id'] ?? null) > 0
                && ($start['user_message_id'] ?? null) > 0
                && ($start['agent_message_id'] ?? null) === ($start['message_id'] ?? null)
                && ($start['status'] ?? null) === 'pending'
                && ($start['conversation_status'] ?? null) === 'pending'
                && ($start['message_status'] ?? null) === 'pending'
                && ($result['status']['conversation']['status'] ?? null) === 'pending'
                && count($messages) === 2
                && ($messages[0]['role'] ?? null) === 'user'
                && ($messages[0]['status'] ?? null) === 'completed'
                && ($messages[1]['role'] ?? null) === 'agent'
                && ($messages[1]['status'] ?? null) === 'pending'
                && ($messages[1]['steps'] ?? null) === [];
        },
        'rollback' => function($result) use($cleanup_conversations) {
            $cleanup_conversations();
        }
    ]
];
