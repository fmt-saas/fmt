# Agent MVP HTTP contract

This contract is the frontend boundary for `automation\agent`. Provider metadata,
OpenAI response identifiers, function calls, tool arguments, raw tool results, and
internal controller names are deliberately not exposed.

All three endpoints are protected and use the current authenticated eQual user.
A conversation owned by another user returns `access_denied`.

## Routes

| Purpose | Method and route | Parameters |
| --- | --- | --- |
| Add a user message | `POST /?do=automation_agent_start` | JSON body: required `message` string; optional `conversation_id` integer; optional `context` object/array |
| Advance one orchestration round | `POST /?do=automation_agent_continue` | JSON body: required `conversation_id` integer |
| Read the complete UI state | `GET /?get=automation_agent_status&conversation_id={id}` | Query parameter: required `conversation_id` integer |

Requests and responses use `application/json; charset=utf-8`.

## `start`

Without `conversation_id`, `start` creates a conversation. With
`conversation_id`, it appends to an owned, pending conversation that has no
pending or running agent message. If `context` is supplied for an existing
conversation, it replaces the stored context.

The action creates these records and returns without invoking the LLM:

```text
Conversation[pending]
  Message[user/completed]
  Message[agent/pending]
```

Example request:

```http
POST /?do=automation_agent_start
Content-Type: application/json

{
  "message": "Pourquoi dois-je encore payer 1240 EUR ?",
  "context": {
    "condo_id": 42
  }
}
```

Response (`201 Created`):

```json
{
  "conversation_id": 123,
  "user_message_id": 457,
  "agent_message_id": 458,
  "message_id": 458,
  "status": "pending",
  "conversation_status": "pending",
  "message_status": "pending"
}
```

`message_id` and `agent_message_id` identify the same pending agent message.

## `continue`

Each call performs exactly one round:

```text
one LLM call -> zero or more requested tools -> return
```

It never loops until a final answer. If tools were executed, their results are
persisted internally and supplied to the next LLM round when the frontend calls
`continue` again.

Example request:

```http
POST /?do=automation_agent_continue
Content-Type: application/json

{
  "conversation_id": 123
}
```

Response after a tool round (`200 OK`):

```json
{
  "conversation_id": 123,
  "message_id": 458,
  "conversation_status": "pending",
  "message_status": "pending",
  "content": null,
  "awaiting_continuation": true
}
```

Response after the final LLM round (`200 OK`):

```json
{
  "conversation_id": 123,
  "message_id": 458,
  "conversation_status": "pending",
  "message_status": "completed",
  "content": "Le solde restant est de 1 240 EUR.",
  "awaiting_continuation": false
}
```

The caller must issue another `continue` only while `message_status` is
`pending`. It stops on `completed` or `failed`.

## `status`

`status` is read-only. It does not invoke the LLM, execute a tool, or change any
`modified` timestamp. Messages and steps are ordered by ascending `sequence`.

Response (`200 OK`):

```json
{
  "conversation": {
    "id": 123,
    "status": "running"
  },
  "messages": [
    {
      "id": 457,
      "sequence": 1,
      "role": "user",
      "status": "completed",
      "content": "Pourquoi dois-je encore payer 1240 EUR ?",
      "steps": []
    },
    {
      "id": 458,
      "sequence": 2,
      "role": "agent",
      "status": "running",
      "content": null,
      "steps": [
        {
          "id": 1001,
          "sequence": 1,
          "type": "llm",
          "status": "completed",
          "code": "analysis",
          "label": "Demande analysée"
        },
        {
          "id": 1002,
          "sequence": 2,
          "type": "tool",
          "status": "running",
          "code": "load_account_summary",
          "label": "Je consulte le solde de votre compte"
        }
      ]
    }
  ],
  "conversation_id": 123,
  "status": "running",
  "agent_message": {
    "id": 458,
    "sequence": 2,
    "role": "agent",
    "status": "running",
    "content": null,
    "steps": [
      {
        "id": 1001,
        "sequence": 1,
        "type": "llm",
        "status": "completed",
        "code": "analysis",
        "label": "Demande analysée"
      },
      {
        "id": 1002,
        "sequence": 2,
        "type": "tool",
        "status": "running",
        "code": "load_account_summary",
        "label": "Je consulte le solde de votre compte"
      }
    ]
  }
}
```

`conversation_id`, top-level `status`, and `agent_message` are compatibility
aliases. New clients should render from `conversation` and `messages`.

The `code` and `label` values for tool steps come from the registered tool UX
metadata. LLM steps use the neutral `analysis` code and never expose reasoning.

## State machines

### Conversation

```text
pending --continue claims the conversation--> running
running --round completed--------------------> pending
running --round failed-----------------------> pending
```

`running` is claimed with one conditional database update. A concurrent second
`continue` therefore fails with `conversation_already_running`. No transaction
is kept open during the provider call.

### Message

```text
user:  created directly as completed

agent: pending --continue--> running --tools require another round--> pending
                                |--final answer---------------------> completed
                                `--unrecoverable error--------------> failed
```

### MessageStep

```text
llm:  running --> completed | failed
tool: running --> completed | failed
```

Each running/completed/failed transition is persisted immediately. The eQual
HTTP entry point disables PHP sessions, so an authenticated `status` request can
run in parallel while `continue` is waiting for the provider or a tool.

A failed tool step does not fail the agent message. Its result is returned to
the LLM as `required_information_unavailable`, and the following round must
explain to the user that the indispensable information could not be determined
without inventing an answer.

## Complete two-round example

The following exchange represents `LLM -> tool -> LLM -> final answer`.

1. `POST /?do=automation_agent_start`

```json
{
  "conversation_id": 123,
  "user_message_id": 457,
  "agent_message_id": 458,
  "message_id": 458,
  "status": "pending",
  "conversation_status": "pending",
  "message_status": "pending"
}
```

2. Initial `GET /?get=automation_agent_status&conversation_id=123`

```json
{
  "conversation": {"id": 123, "status": "pending"},
  "messages": [
    {"id": 457, "sequence": 1, "role": "user", "status": "completed", "content": "Pourquoi dois-je encore payer 1240 EUR ?", "steps": []},
    {"id": 458, "sequence": 2, "role": "agent", "status": "pending", "content": null, "steps": []}
  ],
  "conversation_id": 123,
  "status": "pending",
  "agent_message": {"id": 458, "sequence": 2, "role": "agent", "status": "pending", "content": null, "steps": []}
}
```

3. First `POST /?do=automation_agent_continue`

```json
{
  "conversation_id": 123,
  "message_id": 458,
  "conversation_status": "pending",
  "message_status": "pending",
  "content": null,
  "awaiting_continuation": true
}
```

4. `GET status` after the first round contains two completed steps:

```json
{
  "conversation": {"id": 123, "status": "pending"},
  "messages": [
    {"id": 457, "sequence": 1, "role": "user", "status": "completed", "content": "Pourquoi dois-je encore payer 1240 EUR ?", "steps": []},
    {
      "id": 458,
      "sequence": 2,
      "role": "agent",
      "status": "pending",
      "content": null,
      "steps": [
        {"id": 1001, "sequence": 1, "type": "llm", "status": "completed", "code": "analysis", "label": "Demande analysée"},
        {"id": 1002, "sequence": 2, "type": "tool", "status": "completed", "code": "load_account_summary", "label": "Solde du compte consulté"}
      ]
    }
  ],
  "conversation_id": 123,
  "status": "pending",
  "agent_message": {
    "id": 458,
    "sequence": 2,
    "role": "agent",
    "status": "pending",
    "content": null,
    "steps": [
      {"id": 1001, "sequence": 1, "type": "llm", "status": "completed", "code": "analysis", "label": "Demande analysée"},
      {"id": 1002, "sequence": 2, "type": "tool", "status": "completed", "code": "load_account_summary", "label": "Solde du compte consulté"}
    ]
  }
}
```

5. Second `POST /?do=automation_agent_continue`

```json
{
  "conversation_id": 123,
  "message_id": 458,
  "conversation_status": "pending",
  "message_status": "completed",
  "content": "Le solde restant est de 1 240 EUR.",
  "awaiting_continuation": false
}
```

6. The final `GET status` keeps the first two steps and appends:

```json
{
  "id": 1003,
  "sequence": 3,
  "type": "llm",
  "status": "completed",
  "code": "analysis",
  "label": "Demande analysée"
}
```

The agent message is then `completed` and its `content` contains the final answer.

## Errors

Errors use the standard eQual envelope. For example, a concurrent call returns
HTTP `409 Conflict`:

```json
{
  "errors": {
    "CONFLICT_OBJECT": "conversation_already_running"
  }
}
```

| Error key | HTTP status | Meaning |
| --- | --- | --- |
| `conversation_not_found` | `404` | The identifier does not match a conversation. |
| `access_denied` | `403` | The conversation belongs to another user. |
| `conversation_already_running` | `409` | Another `continue` owns the current round. |
| `no_pending_agent_message` | `409` | There is no agent message to advance. |
| `provider_error` | `500` | The configured LLM provider failed. |
| `agent_failed` | `500` | Another unrecoverable orchestration error occurred. |

After `provider_error` or `agent_failed`, the conversation is restored to
`pending` and the current agent message is `failed`; it is never left `running`.
