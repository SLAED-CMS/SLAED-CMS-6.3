<?php
// Stubs to reduce false positives from bundled legacy libraries.

class PclZip {
    public function __construct(string $filename = '') {}
}

function PclErrorCode(): int { return 0; }
function PclErrorString(): string { return ''; }
function PclError(int $code, string $message = ''): void {}
function PclErrorReset(): void {}
