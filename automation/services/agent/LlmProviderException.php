<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace automation\agent;

class LlmProviderException extends \RuntimeException {

    private array $metadata;

    public function __construct(string $message, int $code, array $metadata = []) {
        parent::__construct($message, $code);
        $this->metadata = $metadata;
    }

    public function metadata(): array {
        return $this->metadata;
    }
}
