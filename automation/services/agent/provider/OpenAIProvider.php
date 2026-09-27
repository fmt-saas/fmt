<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent\provider;

use automation\agent\LlmProviderInterface;
use Exception;

class OpenAIProvider implements LlmProviderInterface {

    private const ENDPOINT = 'https://api.openai.com/v1/responses';
    private const CONNECT_TIMEOUT = 5;

    private string $api_key;
    private string $model;
    private int $timeout;
    /** @var callable|null */
    private $transport;

    public function __construct(string $api_key, string $model, int $timeout = 30, ?callable $transport = null) {
        $api_key = trim($api_key);
        $model = trim($model);

        if($api_key === '' || $model === '' || $timeout <= 0) {
            throw new Exception('openai_missing_configuration', EQ_ERROR_INVALID_CONFIG);
        }

        $this->api_key = $api_key;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->transport = $transport;
    }

    public function name(): string {
        return 'openai';
    }

    public function generate(
        array $messages,
        ?array $continuation_metadata,
        string $instructions,
        array $tools = []
    ): array {
        $previous_response_id = $this->extractPreviousResponseId($continuation_metadata);
        $payload = [
            'model'         => $this->model,
            'instructions'  => $instructions,
            'input'         => $this->mapMessages($messages),
            'store'         => true
        ];

        if(count($tools)) {
            $payload['tools'] = $this->mapTools($tools);
            $payload['tool_choice'] = 'auto';
            $payload['parallel_tool_calls'] = false;
        }

        if($previous_response_id !== null) {
            $payload['previous_response_id'] = $previous_response_id;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        catch(\JsonException $exception) {
            throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
        }

        $response = $this->send($body);
        $request_id = $this->readHeader($response['headers'] ?? [], 'x-request-id');
        $status = (int) ($response['status'] ?? 0);

        if($status < 200 || $status >= 300) {
            throw new Exception(
                serialize([
                    'error'       => 'openai_http_error',
                    'http_status' => $status ?: null,
                    'request_id'  => $request_id
                ]),
                EQ_ERROR_UNKNOWN
            );
        }

        try {
            $data = json_decode((string) ($response['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        }
        catch(\JsonException $exception) {
            throw new Exception(
                serialize([
                    'error'       => 'openai_invalid_response',
                    'http_status' => $status,
                    'request_id'  => $request_id
                ]),
                EQ_ERROR_UNKNOWN
            );
        }

        if(!is_array($data)
            || !isset($data['id'])
            || !is_string($data['id'])
            || trim($data['id']) === ''
            || ($data['status'] ?? null) !== 'completed') {
            throw new Exception(
                serialize([
                    'error'       => 'openai_invalid_response',
                    'http_status' => $status,
                    'request_id'  => $request_id
                ]),
                EQ_ERROR_UNKNOWN
            );
        }

        $text_parts = [];
        $tool_calls = [];
        foreach($data['output'] ?? [] as $output) {
            if(!is_array($output)) {
                continue;
            }

            if(($output['type'] ?? null) === 'function_call') {
                $call_id = $output['call_id'] ?? null;
                $name = $output['name'] ?? null;
                $encoded_arguments = $output['arguments'] ?? null;

                if(!is_string($call_id)
                    || trim($call_id) === ''
                    || !is_string($name)
                    || trim($name) === ''
                    || !is_string($encoded_arguments)) {
                    throw new Exception(
                        serialize([
                            'error'       => 'openai_invalid_response',
                            'http_status' => $status,
                            'request_id'  => $request_id
                        ]),
                        EQ_ERROR_UNKNOWN
                    );
                }

                try {
                    $arguments = json_decode($encoded_arguments, true, 512, JSON_THROW_ON_ERROR);
                }
                catch(\JsonException $exception) {
                    throw new Exception(
                        serialize([
                            'error'       => 'openai_invalid_response',
                            'http_status' => $status,
                            'request_id'  => $request_id
                        ]),
                        EQ_ERROR_UNKNOWN
                    );
                }

                if(!is_array($arguments)) {
                    throw new Exception(
                        serialize([
                            'error'       => 'openai_invalid_response',
                            'http_status' => $status,
                            'request_id'  => $request_id
                        ]),
                        EQ_ERROR_UNKNOWN
                    );
                }

                $tool_calls[] = [
                    'call_id'   => trim($call_id),
                    'name'      => trim($name),
                    'arguments' => $arguments
                ];
                continue;
            }

            if(($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach($output['content'] ?? [] as $content) {
                if(!is_array($content) || ($content['type'] ?? null) !== 'output_text') {
                    continue;
                }

                $text = trim((string) ($content['text'] ?? ''));
                if($text !== '') {
                    $text_parts[] = $text;
                }
            }
        }

        if(!count($text_parts) && !count($tool_calls)) {
            throw new Exception(
                serialize([
                    'error'       => 'openai_empty_response',
                    'http_status' => $status,
                    'request_id'  => $request_id
                ]),
                EQ_ERROR_UNKNOWN
            );
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return [
            'text'      => implode("\n", $text_parts),
            'tool_calls' => $tool_calls,
            'metadata'  => [
                'provider'              => $this->name(),
                'response_id'           => $data['id'],
                'previous_response_id'  => $previous_response_id,
                'model'                 => is_string($data['model'] ?? null) ? $data['model'] : $this->model,
                'response_status'       => $data['status'],
                'request_id'            => $request_id,
                'usage'                 => [
                    'input_tokens'  => (int) ($usage['input_tokens'] ?? 0),
                    'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                    'total_tokens'  => (int) ($usage['total_tokens'] ?? 0)
                ]
            ]
        ];
    }

    private function mapTools(array $tools): array {
        $result = [];

        foreach($tools as $tool) {
            if(!is_array($tool)
                || !is_string($tool['name'] ?? null)
                || trim($tool['name']) === ''
                || !is_string($tool['description'] ?? null)
                || trim($tool['description']) === ''
                || !is_array($tool['parameters'] ?? null)) {
                throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
            }

            $parameters = $tool['parameters'];
            if(($parameters['type'] ?? null) !== 'object') {
                throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
            }

            if(empty($parameters['properties'])) {
                $parameters['properties'] = new \stdClass();
            }

            $parameters['additionalProperties'] = false;
            $property_names = array_keys((array) $parameters['properties']);
            $required_names = is_array($parameters['required'] ?? null)
                ? $parameters['required']
                : [];
            $strict = !count(array_diff($property_names, $required_names));

            $result[] = [
                'type'        => 'function',
                'name'        => trim($tool['name']),
                'description' => trim($tool['description']),
                'parameters'  => $parameters,
                'strict'      => $strict
            ];
        }

        return $result;
    }

    private function mapMessages(array $messages): array {
        $result = [];

        foreach($messages as $message) {
            if(!is_array($message)) {
                throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
            }

            $role = $message['role'] ?? null;
            if($role === 'tool') {
                $call_id = trim((string) ($message['call_id'] ?? ''));
                if($call_id === '' || !array_key_exists('content', $message)) {
                    throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
                }

                $content = $message['content'];
                if(!is_string($content)) {
                    try {
                        $content = json_encode(
                            $content,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        );
                    }
                    catch(\JsonException $exception) {
                        throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
                    }
                }

                $result[] = [
                    'type'    => 'function_call_output',
                    'call_id' => $call_id,
                    'output'  => $content
                ];
                continue;
            }

            if($role === 'user') {
                $provider_role = 'user';
            }
            elseif($role === 'agent') {
                $provider_role = 'assistant';
            }
            else {
                throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
            }

            $content = trim((string) ($message['content'] ?? ''));
            if($content === '') {
                throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
            }

            $result[] = [
                'role'    => $provider_role,
                'content' => $content
            ];
        }

        if(!count($result)) {
            throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
        }

        return $result;
    }

    private function extractPreviousResponseId(?array $metadata): ?string {
        if($metadata === null) {
            return null;
        }

        $response_id = $metadata['response_id'] ?? null;
        if(!is_string($response_id) || trim($response_id) === '') {
            throw new Exception('openai_invalid_response', EQ_ERROR_UNKNOWN);
        }

        return trim($response_id);
    }

    private function send(string $body): array {
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        trigger_error(
            'APP::OpenAI request [POST ' . self::ENDPOINT . ']: ' . $body,
            EQ_REPORT_DEBUG
        );

        if($this->transport !== null) {
            $response = call_user_func(
                $this->transport,
                'POST',
                self::ENDPOINT,
                $headers,
                $body,
                self::CONNECT_TIMEOUT,
                $this->timeout
            );
        }
        else {
            $response = $this->sendWithCurl($headers, $body);
        }

        trigger_error(
            'APP::OpenAI response [POST ' . self::ENDPOINT . ']: '
            . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            EQ_REPORT_DEBUG
        );

        if(!is_array($response)) {
            throw new Exception('openai_transport_error', EQ_ERROR_UNKNOWN);
        }

        if(($response['error'] ?? null) === 'timeout') {
            throw new Exception('openai_timeout', EQ_ERROR_UNKNOWN);
        }

        if(!empty($response['error'])) {
            throw new Exception('openai_transport_error', EQ_ERROR_UNKNOWN);
        }

        return $response;
    }

    private function sendWithCurl(array $headers, string $body): array {
        if(!function_exists('curl_init')) {
            throw new Exception('openai_transport_error', EQ_ERROR_UNKNOWN);
        }

        $response_headers = [];
        $handle = curl_init(self::ENDPOINT);
        if($handle === false) {
            throw new Exception('openai_transport_error', EQ_ERROR_UNKNOWN);
        }

        curl_setopt_array($handle, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HEADERFUNCTION => function($curl, string $line) use(&$response_headers): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if(count($parts) === 2) {
                    $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            }
        ]);

        $response_body = curl_exec($handle);
        $error_number = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if($error_number === CURLE_OPERATION_TIMEDOUT) {
            return ['error' => 'timeout'];
        }

        if($error_number !== CURLE_OK || $response_body === false) {
            return ['error' => 'transport'];
        }

        return [
            'status'  => $status,
            'headers' => $response_headers,
            'body'    => $response_body
        ];
    }

    private function readHeader(array $headers, string $name): ?string {
        $name = strtolower($name);
        foreach($headers as $header_name => $value) {
            if(strtolower((string) $header_name) === $name) {
                $value = trim((string) $value);
                return $value === '' ? null : $value;
            }
        }

        return null;
    }
}
