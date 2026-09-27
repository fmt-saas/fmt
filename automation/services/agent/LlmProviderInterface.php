<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent;

interface LlmProviderInterface {

    public function name(): string;

    /**
     * @param array       $messages               Provider-independent input items. Business messages use
     *                                              the roles user and agent; tool results use the role tool.
     * @param array|null  $continuation_metadata  Opaque metadata returned by a previous LLM call.
     * @param string      $instructions           Provider-independent system instructions.
     * @param array       $tools                  Provider-independent tool definitions.
     *
     * @return array{text: string, tool_calls: array, metadata: array}
     * @throws \Exception When the provider cannot generate a valid response.
     */
    public function generate(
        array $messages,
        ?array $continuation_metadata,
        string $instructions,
        array $tools = []
    ): array;
}
