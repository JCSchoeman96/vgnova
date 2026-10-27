<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$test_root = __DIR__;
$runners = [
    'run-person-relationship-source-tests.php',
    'run-person-relationship-storage-tests.php',
    'run-person-relationship-query-tests.php',
    'run-person-relationship-indexer-tests.php',
    'run-person-relationship-admin-tests.php',
    'run-person-relationship-cli-tests.php',
];

foreach ($runners as $runner) {
    $process = proc_open(
        [PHP_BINARY, $test_root . '/' . $runner],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
    );
    if (! is_resource($process)) {
        fwrite(STDERR, "Could not start {$runner}.\n");
        exit(1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($stdout !== '') fwrite(STDOUT, $stdout);
    if ($stderr !== '') fwrite(STDERR, $stderr);
    if ($status !== 0) {
        fwrite(STDERR, "Person relationship suite failed in {$runner}.\n");
        exit($status);
    }
}

$patch = file_get_contents($root . '/site-code/voelgoed-platform-core/integration.patch');
if (! is_string($patch)) {
    fwrite(STDERR, "Could not read Platform Core integration patch.\n");
    exit(1);
}

$classes = [
    'PersonRelationshipContract', 'PersonRelationshipSchema', 'PersonRelationshipSourceLock',
    'PersonRelationshipRepository', 'PersonRelationshipSourceReader', 'PersonRelationshipValidator',
    'PersonRelationshipIndexer', 'PersonRelationshipReconciler', 'PersonRelationshipService',
    'PersonRelationshipAdmin', 'PersonRelationshipCli',
];
foreach ($classes as $class) {
    if (! str_contains($patch, "includes/Person/{$class}.php")) {
        fwrite(STDERR, "Integration patch does not load {$class}.\n");
        exit(1);
    }
}
foreach (['person_relationship_admin', 'person_relationship_cli'] as $component) {
    if (! str_contains($patch, "'{$component}'")) {
        fwrite(STDERR, "Integration patch does not register {$component}.\n");
        exit(1);
    }
}
if (str_contains($patch, 'PersonRelationshipSchema::install') || str_contains($patch, '->install()')) {
    fwrite(STDERR, "Integration patch must not install the Person relationship schema automatically.\n");
    exit(1);
}

$inventory_json = file_get_contents($root . '/site-code/runtime-tools/mutating-entrypoints.json');
$inventory = is_string($inventory_json) ? json_decode($inventory_json, true) : null;
if (! is_array($inventory) || ! is_array($inventory['entries'] ?? null)) {
    fwrite(STDERR, "Could not read the runtime mutation inventory.\n");
    exit(1);
}
$entries = [];
foreach ($inventory['entries'] as $entry) {
    if (is_array($entry) && is_string($entry['path'] ?? null)) {
        $entries[$entry['path']] = $entry;
    }
}
$expected_classifications = [
    'PersonRelationshipContract' => 'library',
    'PersonRelationshipSourceReader' => 'library',
    'PersonRelationshipValidator' => 'library',
    'PersonRelationshipReconciler' => 'library',
    'PersonRelationshipService' => 'library',
    'PersonRelationshipSourceLock' => 'library',
    'PersonRelationshipRepository' => 'explicit_exemption',
    'PersonRelationshipSchema' => 'explicit_exemption',
    'PersonRelationshipIndexer' => 'explicit_exemption',
    'PersonRelationshipAdmin' => 'explicit_exemption',
    'PersonRelationshipCli' => 'mutating',
];
foreach ($expected_classifications as $class => $classification) {
    $path = "site-code/voelgoed-platform-core/includes/Person/{$class}.php";
    if (($entries[$path]['classification'] ?? null) !== $classification) {
        fwrite(STDERR, "Mutation inventory classification for {$class} must be {$classification}.\n");
        exit(1);
    }
}
$expected_exemptions = [
    'PersonRelationshipRepository' => [
        'reason' => 'The repository persists only the disposable Person relationship projection for the ordinary indexer and guarded maintenance commands.',
        'reviewed_scope' => 'Replace or delete index rows for one source without changing authoritative ACF values.',
    ],
    'PersonRelationshipSchema' => [
        'reason' => 'Schema installation is available only through the mutation-guarded Person relationship setup command.',
        'reviewed_scope' => 'Create or verify the derived Person relationship index table and version option.',
    ],
    'PersonRelationshipIndexer' => [
        'reason' => 'The indexer writes the disposable projection during ordinary source saves and through guarded maintenance commands.',
        'reviewed_scope' => 'Replace or remove derived rows and update the dirty marker for one supported Article or Course source.',
    ],
    'PersonRelationshipAdmin' => [
        'reason' => 'Normal WordPress Article and Course editor callbacks remain outside administrative runtime coordination.',
        'reviewed_scope' => 'ACF save, REST save, publication validation, and permanent-delete hooks that validate or project canonical Person relationships.',
    ],
];
foreach ($expected_exemptions as $class => $required_text) {
    $entry = $entries["site-code/voelgoed-platform-core/includes/Person/{$class}.php"] ?? [];
    foreach ($required_text as $field => $text) {
        if (($entry[$field] ?? null) !== $text) {
            fwrite(STDERR, "Mutation inventory {$field} for {$class} must exactly match the approved narrow scope.\n");
            exit(1);
        }
    }
    if (($entry['classification'] ?? null) !== 'explicit_exemption') {
        fwrite(STDERR, "Mutation inventory exemption for {$class} is missing.\n");
        exit(1);
    }
}
$cli_entry = $entries['site-code/voelgoed-platform-core/includes/Person/PersonRelationshipCli.php'] ?? [];
if (($cli_entry['session_guard_required'] ?? null) !== true
    || ($cli_entry['guard_function'] ?? null) !== 'vg_runtime_mutation_assert_session'
    || ($cli_entry['purpose'] ?? null) !== 'maintenance') {
    fwrite(STDERR, "PersonRelationshipCli must require the maintenance session guard.\n");
    exit(1);
}
if (($entries['site-code/voelgoed-platform-core/tests/run-person-relationship-tests.php']['classification'] ?? null) !== 'test_only') {
    fwrite(STDERR, "Aggregate Person relationship runner must be classified test_only.\n");
    exit(1);
}

fwrite(STDOUT, "PASS Person relationship assembly loads all classes and registers lifecycle without schema auto-install.\n");
