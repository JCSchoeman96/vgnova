<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-core.php';
require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-database.php';

/** @return never */
function vg_runtime_mutation_cli_error(VgRuntimeMutationException $exception): never
{
    fwrite(STDERR, 'ERROR ' . $exception->errorCode . ': ' . $exception->getMessage() . "\n");
    exit($exception->exitCode);
}

/** @return array<string,string|bool> */
function vg_runtime_mutation_cli_parse(array $arguments): array
{
    $options = [];
    $optionArguments = [];
    $childCommand = null;
    foreach ($arguments as $argument) {
        if ($childCommand !== null) {
            $childCommand[] = $argument;
            continue;
        }
        if ($argument === '--') {
            $childCommand = [];
            continue;
        }
        if ($argument === '--status') {
            if (isset($options['status'])) {
                throw new VgRuntimeMutationException(71, 'DUPLICATE_OPTION', 'Coordinator option was supplied more than once.');
            }
            $options['status'] = true;
            $optionArguments[] = $argument;
            continue;
        }
        if (! preg_match('/\A--(target|wp-root|purpose|db-socket)=(.*)\z/s', $argument, $matches)) {
            throw new VgRuntimeMutationException(71, 'INVALID_ARGUMENTS', 'Coordinator arguments are invalid.');
        }
        $name = $matches[1];
        if (array_key_exists($name, $options)) {
            throw new VgRuntimeMutationException(71, 'DUPLICATE_OPTION', 'Coordinator option was supplied more than once.');
        }
        $options[$name] = $matches[2];
        $optionArguments[] = $argument;
    }

    VgRuntimeMutationCore::validateArguments($optionArguments);
    $options['command'] = $childCommand;
    return $options;
}

/** @param array<string,mixed> $metadata @return array<string,mixed> */
function vg_runtime_mutation_cli_safe_metadata(array $metadata): array
{
    $safe = [];
    foreach ([
        'schema_version', 'target', 'purpose', 'pid', 'hostname', 'started_at_utc',
        'repository_commit', 'working_directory', 'command_summary', 'state',
        'session_id', 'database_lock_name', 'database_connection_id',
        'released_at_utc', 'child_exit_code',
    ] as $key) {
        if (! array_key_exists($key, $metadata)) {
            continue;
        }
        $value = $metadata[$key];
        if (is_string($value) || is_int($value) || $value === null) {
            $safe[$key] = $value;
        }
    }
    return $safe;
}

try {
    if (PHP_SAPI !== 'cli') {
        throw new VgRuntimeMutationException(70, 'CLI_ONLY', 'Runtime mutation coordination requires PHP CLI.');
    }

    $options = vg_runtime_mutation_cli_parse(array_slice($argv, 1));
    $registry = VgRuntimeMutationCore::loadTargetRegistry(dirname(__DIR__) . '/runtime-tools/runtime-mutation-targets.json');
    $target = VgRuntimeMutationCore::resolveTarget($registry, (string) ($options['target'] ?? ''));
    $wpRoot = (string) ($options['wp-root'] ?? '');
    if (! VgRuntimeMutationCore::isAbsolutePathForDatabase($wpRoot) || ! is_dir($wpRoot)) {
        throw new VgRuntimeMutationException(71, 'INVALID_WORDPRESS_ROOT', 'WordPress root must be an existing absolute path.');
    }
    $resolvedRoot = realpath($wpRoot);
    if (! is_string($resolvedRoot)) {
        throw new VgRuntimeMutationException(71, 'INVALID_WORDPRESS_ROOT', 'WordPress root could not be resolved.');
    }

    $socketOverride = isset($options['db-socket']) && $options['db-socket'] !== ''
        ? (string) $options['db-socket']
        : (getenv('VGNOVA_DB_SOCKET') ?: null);
    if ($socketOverride !== null && ! VgRuntimeMutationCore::isAbsolutePathForDatabase($socketOverride)) {
        throw new VgRuntimeMutationException(71, 'INVALID_DB_SOCKET', 'Database socket path must be absolute.');
    }

    $repositoryRoot = dirname(__DIR__, 2);
    $stateDirectory = VgRuntimeMutationCore::stateDirectory(null, [$resolvedRoot, $repositoryRoot]);
    $lockFile = $stateDirectory . '/' . $target['id'] . '.lock';
    $lockName = VgRuntimeMutationCore::advisoryLockName($target);
    if (($options['status'] ?? false) === true) {
        if (is_array($options['command']) || isset($options['purpose'])) {
            throw new VgRuntimeMutationException(71, 'INVALID_STATUS_ARGUMENTS', 'Status does not accept a mutation command or purpose.');
        }
        $filesystem = VgRuntimeMutationCore::probeFilesystemLock($lockFile);
        $databaseStatus = VgRuntimeMutationDatabase::statusLockOwner($target, $resolvedRoot, $lockName, $socketOverride);
        $response = [
            'target' => $target['id'],
            'lock_file' => $lockFile,
            'filesystem_lock_held' => $filesystem['held'],
            'database_lock_name' => $lockName,
            'database_lock_state' => $databaseStatus['state'],
            'database_lock_held' => match ($databaseStatus['state']) {
                'held' => true,
                'free' => false,
                default => null,
            },
            'database_lock_owner_connection_id' => $databaseStatus['owner_connection_id'],
            'database_lock_error_code' => $databaseStatus['error_code'],
            'metadata' => is_array($filesystem['metadata'])
                ? vg_runtime_mutation_cli_safe_metadata($filesystem['metadata'])
                : null,
        ];
        fwrite(STDOUT, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    if (! is_string($options['purpose'] ?? null)) {
        throw new VgRuntimeMutationException(71, 'PURPOSE_REQUIRED', 'A short human-readable purpose is required.');
    }
    $purpose = VgRuntimeMutationCore::validatePurpose($options['purpose']);
    $childArguments = $options['command'];
    if (! is_array($childArguments) || $childArguments === []) {
        throw new VgRuntimeMutationException(71, 'CHILD_COMMAND_REQUIRED', 'A child command after -- is required.');
    }
    foreach ($childArguments as $argument) {
        if (! is_string($argument)) {
            throw new VgRuntimeMutationException(71, 'INVALID_CHILD_ARGUMENT', 'Child command arguments must be strings.');
        }
    }

    $lease = null;
    $revision = null;
    $git = @proc_open(VgRuntimeMutationCore::descriptorSafeCommand(
        ['git', '-C', $repositoryRoot, 'rev-parse', '--verify', 'HEAD'],
        []
    ), [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $gitPipes, null, VgRuntimeMutationCore::safeEnvironment(getenv() ?: []));
    if (is_resource($git)) {
        $gitOutput = trim((string) stream_get_contents($gitPipes[1]));
        fclose($gitPipes[1]);
        $gitStatus = proc_close($git);
        if ($gitStatus === 0 && preg_match('/\A[a-f0-9]{40}\z/', $gitOutput)) {
            $revision = $gitOutput;
        }
    }

    $exitCode = VgRuntimeMutationCore::withSession(
        $lockFile,
        $target,
        [
            'purpose' => $purpose,
            'command' => $childArguments,
            'repository_commit' => $revision,
            'working_directory' => getcwd() ?: '',
        ],
        static function (string $requestedLockName) use (&$lease, $target, $resolvedRoot, $socketOverride): array {
            $lease = VgRuntimeMutationDatabase::startKeeper($target, $resolvedRoot, $requestedLockName, $socketOverride);
            return $lease;
        },
        static function (array $registeredTarget, array $observedIdentity) use ($resolvedRoot): void {
            VgRuntimeMutationCore::validateTargetIdentity($registeredTarget, $observedIdentity, $resolvedRoot);
        },
        static function (array $proof, $lockHandle, $lifetimeHandle) use ($childArguments, $resolvedRoot): int {
            $baseEnvironment = getenv() ?: [];
            $baseEnvironment['VGNOVA_MUTATION_WP_ROOT'] = $resolvedRoot;
            $environment = VgRuntimeMutationCore::childEnvironment($baseEnvironment, $proof);
            return VgRuntimeMutationCore::runChildCommand($childArguments, $environment, $lockHandle, $lifetimeHandle);
        },
        static function (string $requestedLockName) use (&$lease): void {
            if (is_array($lease)) {
                VgRuntimeMutationDatabase::releaseKeeper($lease);
                $lease = null;
            }
        }
    );
    exit($exitCode);
} catch (VgRuntimeMutationException $exception) {
    vg_runtime_mutation_cli_error($exception);
} catch (Throwable) {
    fwrite(STDERR, "ERROR INTERNAL_FAILURE: Runtime mutation coordination failed.\n");
    exit(70);
}
