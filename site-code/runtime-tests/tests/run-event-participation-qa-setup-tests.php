<?php
declare(strict_types=1);

$setup_file = dirname(__DIR__) . '/event-participation-qa-setup.php';
if (! is_file($setup_file)) {
    fwrite(STDERR, "FAIL QA setup failures are reported before mutation: setup boundary is missing.\n");
    exit(1);
}

require_once $setup_file;

$setup = vg_event_participation_qa_initialize(
    static function (): string {
        return 'test-token';
    },
    static function (): object {
        throw new RuntimeException('service setup failed');
    }
);

if (($setup['failure'] ?? null) !== 'service setup failed' || ($setup['token'] ?? null) !== null) {
    fwrite(STDERR, "FAIL QA setup failures are reported before mutation: service exception was not captured.\n");
    exit(1);
}

$report = vg_event_participation_qa_setup_failure_report($setup['failure']);
if (($report['result'] ?? null) !== 'FAIL'
    || ($report['phase'] ?? null) !== 'setup'
    || ($report['mutation_started'] ?? null) !== false
    || ($report['failures'][0]['message'] ?? null) !== 'service setup failed'
) {
    fwrite(STDERR, "FAIL QA setup failures are reported before mutation: failure report contract changed.\n");
    exit(1);
}

$token_setup = vg_event_participation_qa_initialize(
    static function (): string {
        throw new RuntimeException('token generation failed');
    },
    static fn (): object => new stdClass()
);
if (($token_setup['failure'] ?? null) !== 'token generation failed' || ($token_setup['service'] ?? null) !== null) {
    fwrite(STDERR, "FAIL QA setup failures are reported before mutation: token exception was not captured.\n");
    exit(1);
}

fwrite(STDOUT, "PASS QA setup failures are reported before mutation.\n");
