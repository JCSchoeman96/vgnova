<?php
declare(strict_types=1);

require_once __DIR__ . '/../check-person-publication-source.php';

function publication_policy_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$repositoryRoot = dirname(__DIR__, 3);
$unauthorizedPath = 'site-code/bin/tests/fixtures/production/unauthorized-publication.php';
$ignoredPath = 'site-code/bin/tests/fixtures/ignored/ignored-publication.php';
$unauthorized = vg_person_publication_source_check_files($repositoryRoot, [$unauthorizedPath], true);
publication_policy_assert(! $unauthorized['valid'], 'Unauthorized production fixture unexpectedly passed.');
publication_policy_assert(
    str_contains(implode("\n", $unauthorized['errors']), $unauthorizedPath . ':2'),
    'Failure did not identify the offending repository path and line.'
);
$testOnly = vg_person_publication_source_check_files($repositoryRoot, [$ignoredPath]);
publication_policy_assert($testOnly['valid'], 'Explicitly classified test-only fixture caused a false positive.');

echo "Person publication source policy tests passed.\n";
