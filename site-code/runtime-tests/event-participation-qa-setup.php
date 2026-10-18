<?php
declare(strict_types=1);

function vg_event_participation_qa_initialize(callable $token_factory, callable $service_factory): array
{
    try {
        $token = $token_factory();
        $service = $service_factory();

        return [
            'token' => $token,
            'owner' => '[QA][AUTOMATED] ' . $token,
            'service' => $service,
            'failure' => null,
        ];
    } catch (Throwable $error) {
        return [
            'token' => null,
            'owner' => null,
            'service' => null,
            'failure' => $error->getMessage(),
        ];
    }
}

function vg_event_participation_qa_setup_failure_report(string $message): array
{
    return [
        'result' => 'FAIL',
        'phase' => 'setup',
        'mutation_started' => false,
        'failures' => [['phase' => 'setup', 'message' => $message]],
    ];
}
