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

foreach ([
    'namespaced-call.php' => "<?php namespace Example; \\wp_publish_post(\$post);",
    'dynamic-variable-call.php' => "<?php \$fn = 'wp_publish_post'; \$fn(\$post);",
    'dynamic-dispatch.php' => "<?php call_user_func('wp_publish_post', \$post);",
    'dynamic-dispatch-array.php' => "<?php call_user_func_array('wp_publish_post', [\$post]);",
] as $name => $source) {
    $fixture = 'site-code/bin/tests/fixtures/production/' . $name;
    file_put_contents($repositoryRoot . '/' . $fixture, $source);
    $result = vg_person_publication_source_check_files($repositoryRoot, [$fixture], true);
    publication_policy_assert(! $result['valid'], $name . ' unexpectedly passed.');
    publication_policy_assert(str_contains(implode("\n", $result['errors']), $fixture), $name . ' failure omitted its path.');
    unlink($repositoryRoot . '/' . $fixture);
}

publication_policy_assert(vg_person_publication_source_is_test_only('packages/example/tests/case.php'), 'Nested tests path was not excluded.');
publication_policy_assert(vg_person_publication_source_is_test_only('site-code/bin/tests/fixtures/production/unauthorized-publication.php'), 'Production fixture was not explicitly classified as test-only.');
publication_policy_assert(vg_person_publication_source_is_excluded('vendor/package/source.php'), 'Vendor path was not excluded.');

$temporaryProductionPath = 'site-code/bin/untracked-publication-policy-fixture.php';
file_put_contents($repositoryRoot . '/' . $temporaryProductionPath, "<?php \\wp_publish_post(\$post);");
$repositoryScan = vg_person_publication_source_check_repository($repositoryRoot);
publication_policy_assert(! $repositoryScan['valid'], 'Untracked production PHP escaped the repository scan.');
publication_policy_assert(str_contains(implode("\n", $repositoryScan['errors']), $temporaryProductionPath), 'Untracked production failure omitted its path.');
unlink($repositoryRoot . '/' . $temporaryProductionPath);

$vendorRoot = $repositoryRoot . '/site-code/bin/vendor/person-publication-policy-fixture';
mkdir($vendorRoot, 0777, true);
file_put_contents($vendorRoot . '/vendor-publication.php', "<?php wp_publish_post(\$post);");
$vendorScan = vg_person_publication_source_check_files($repositoryRoot, ['site-code/bin/vendor/person-publication-policy-fixture/vendor-publication.php']);
publication_policy_assert($vendorScan['valid'] && $vendorScan['files_scanned'] === 0, 'Vendor PHP was scanned as repository-owned code.');
unlink($vendorRoot . '/vendor-publication.php');
rmdir($vendorRoot);
rmdir(dirname($vendorRoot));

echo "Person publication source policy tests passed.\n";
