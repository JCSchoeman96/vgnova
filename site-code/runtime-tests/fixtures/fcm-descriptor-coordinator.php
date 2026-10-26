<?php
declare(strict_types=1);

require_once __DIR__ . '/../../runtime-tools/runtime-mutation-core.php';

$python = $argv[1] ?? '';
if (! is_string($python) || $python === '' || ! str_starts_with($python, '/')) {
    fwrite(STDERR, "Python executable must be an absolute path.\n");
    exit(2);
}

$directory = sys_get_temp_dir() . '/vg-pe007-fcm-fd-' . bin2hex(random_bytes(6));
$fakeBin = $directory . '/bin';
$fakeWpRoot = $directory . '/wp-root';
if (! mkdir($fakeBin, 0700, true) || ! mkdir($fakeWpRoot, 0700, true)) {
    fwrite(STDERR, "Could not create the source-safe FCM fixture.\n");
    exit(2);
}

$lockFile = $directory . '/host.lock';
$lockHandle = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
if (! is_resource($lockHandle)) {
    fwrite(STDERR, "Could not acquire the source-safe host lock.\n");
    exit(2);
}

$fakeWp = <<<'PY'
#!/usr/bin/env python3
import json
import os
import sys

path_arg = next((item[7:] for item in sys.argv[1:] if item.startswith("--path=")), None)
if path_arg is None:
    raise SystemExit(81)
def descriptor_exists(number):
    try:
        os.fstat(number)
        return True
    except OSError:
        return False

descriptor_state = {
    "fd3": descriptor_exists(3),
    "coordinator_fds": {str(number): descriptor_exists(number) for number in range(4, 9)},
}
session_state = {
    name: os.environ.get(name)
    for name in (
        "VGNOVA_MUTATION_SESSION_ID",
        "VGNOVA_MUTATION_TARGET",
        "VGNOVA_MUTATION_DB_LOCK_A",
        "VGNOVA_MUTATION_DB_CONNECTION_ID_A",
        "VGNOVA_MUTATION_DB_LOCK_B",
        "VGNOVA_MUTATION_DB_CONNECTION_ID_B",
        "VGNOVA_MUTATION_LOCK_FILE",
    )
}
with open(os.path.join(path_arg, "fake-wp-capture.json"), "w", encoding="utf-8") as capture:
    json.dump({"argv": sys.argv[1:], **descriptor_state, "session": session_state}, capture)
print(json.dumps({"ok": True}))
PY;
file_put_contents($fakeBin . '/wp', $fakeWp);
chmod($fakeBin . '/wp', 0700);

$targetProgram = <<<'PY'
import importlib.util
import json
import os
import sys

def descriptor_exists(number):
    try:
        os.fstat(number)
        return True
    except OSError:
        return False

def descriptor_state():
    return {
        "fd3": descriptor_exists(3),
        "coordinator_fds": {str(number): descriptor_exists(number) for number in range(4, 9)},
    }

spec = importlib.util.spec_from_file_location("fcm_runtime", sys.argv[1])
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
target_fds_before_nested = descriptor_state()
result = module.invoke_runtime("snapshot")
target_fds_after_nested = descriptor_state()
with open(sys.argv[2], "w", encoding="utf-8") as capture:
    json.dump({"before_nested": target_fds_before_nested, "after_nested": target_fds_after_nested, "result": result}, capture)
PY;
$targetCapture = $directory . '/target-capture.json';
$baseEnvironment = [
    'PATH' => $fakeBin . PATH_SEPARATOR . (string) getenv('PATH'),
    'HOME' => $directory,
    'TMPDIR' => $directory,
    'XDG_STATE_HOME' => $directory . '/state',
    'VGNOVA_MUTATION_WP_ROOT' => $fakeWpRoot,
    'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
    'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
    'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '12345',
    'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('b', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '12346',
    'VGNOVA_MUTATION_LOCK_FILE' => $lockFile,
];
$exitCode = 1;
try {
    $exitCode = VgRuntimeMutationCore::runChildCommand(
        [$python, '-c', $targetProgram, __DIR__ . '/../fcm-self-featured-integration.py', $targetCapture],
        $baseEnvironment,
        $lockHandle
    );
    if ($exitCode !== 0) {
        throw new RuntimeException('FCM child exited with status ' . $exitCode . '.');
    }
    $target = json_decode((string) file_get_contents($targetCapture), true, 16, JSON_THROW_ON_ERROR);
    $nested = json_decode((string) file_get_contents($fakeWpRoot . '/fake-wp-capture.json'), true, 16, JSON_THROW_ON_ERROR);
    $expectedControlFds = ['4' => false, '5' => false, '6' => false, '7' => false, '8' => false];
    if (($target['before_nested']['fd3'] ?? null) !== true
        || ($target['before_nested']['coordinator_fds'] ?? null) !== $expectedControlFds
        || ($target['after_nested']['fd3'] ?? null) !== true
        || ($target['after_nested']['coordinator_fds'] ?? null) !== $expectedControlFds) {
        throw new RuntimeException('FCM target must inherit FD3 only; coordinator descriptors 4-8 must be absent.');
    }
    if (($nested['fd3'] ?? null) !== true || ($nested['coordinator_fds'] ?? null) !== $expectedControlFds) {
        throw new RuntimeException('Nested WP-CLI must inherit FD3 only; coordinator descriptors 4-8 must be absent.');
    }
    if (($nested['argv'] ?? null) !== [
        '--path=' . $fakeWpRoot,
        '--quiet',
        'eval-file',
        __DIR__ . '/../fcm-self-featured-cli.php',
    ]) {
        throw new RuntimeException('Nested WP-CLI argv boundaries changed.');
    }
    if (($nested['session'] ?? null) !== [
        'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
        'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
        'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
        'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '12345',
        'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('b', 40),
        'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '12346',
        'VGNOVA_MUTATION_LOCK_FILE' => $lockFile,
    ]) {
        throw new RuntimeException('Nested WP-CLI did not receive the dual-lock session environment.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    foreach ([$fakeBin, $fakeWpRoot] as $path) {
        foreach (glob($path . '/*') ?: [] as $child) {
            @unlink($child);
        }
        @rmdir($path);
    }
    foreach (glob($directory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($directory);
}
exit($exitCode === 0 ? 0 : 1);
