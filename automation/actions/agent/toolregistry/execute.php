<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

use identity\User;
use realestate\ownership\Owner;

[$params, $providers] = eQual::announce([
    'description'   => 'Execute one registered agent tool in the context of the authenticated user.',
    'params'        => [
        'tool_name' => [
            'type'        => 'string',
            'description' => 'Registered tool name.',
            'required'    => true
        ],
        'arguments' => [
            'type'        => 'array',
            'description' => 'Arguments supplied by the LLM for the registered tool.',
            'default'     => []
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

$registry = eQual::run('get', 'automation_agent_tools');
$registered_tools = is_array($registry['tools'] ?? null) ? $registry['tools'] : [];
$tool = null;

foreach($registered_tools as $candidate) {
    if(is_array($candidate) && ($candidate['name'] ?? null) === $params['tool_name']) {
        $tool = $candidate;
        break;
    }
}

if($tool === null) {
    throw new Exception('unknown_tool', EQ_ERROR_INVALID_PARAM);
}

$arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
$schema = is_array($tool['parameters'] ?? null) ? $tool['parameters'] : [];
$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
$required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

foreach($arguments as $name => $value) {
    if(!is_string($name) || !array_key_exists($name, $properties)) {
        throw new Exception('invalid_tool_arguments', EQ_ERROR_INVALID_PARAM);
    }

    $property = $properties[$name];
    if(($property['type'] ?? null) === 'integer'
        && (!is_int($value) || $value < (int) ($property['minimum'] ?? PHP_INT_MIN))) {
        throw new Exception('invalid_tool_arguments', EQ_ERROR_INVALID_PARAM);
    }
}

foreach($required as $name) {
    if(!array_key_exists($name, $arguments)) {
        throw new Exception('invalid_tool_arguments', EQ_ERROR_INVALID_PARAM);
    }
}

if(array_key_exists('ownership_id', $arguments)) {
    $user = User::id($auth->userId())
        ->read(['identity_id'])
        ->first();

    if(!$user) {
        throw new Exception('unknown_user', EQ_ERROR_UNKNOWN_OBJECT);
    }

    if(!$user['identity_id']) {
        throw new Exception('missing_user_identity', EQ_ERROR_INVALID_CONFIG);
    }

    $owner = Owner::search([
            ['identity_id', '=', $user['identity_id']],
            ['ownership_id', '=', $arguments['ownership_id']]
        ])
        ->read(['id'])
        ->first();

    if(!$owner) {
        throw new Exception('ownership_access_denied', EQ_ERROR_NOT_ALLOWED);
    }
}

$handler = $tool['handler'] ?? null;
if(!is_string($handler) || !str_starts_with($handler, 'automation_agent_tools_')) {
    throw new Exception('invalid_tool_handler', EQ_ERROR_INVALID_CONFIG);
}

$result = eQual::run('get', $handler, $arguments);

$context->httpResponse()
    ->status(200)
    ->body([
        'name'   => $tool['name'],
        'ux'     => $tool['ux'],
        'result' => $result
    ])
    ->send();
