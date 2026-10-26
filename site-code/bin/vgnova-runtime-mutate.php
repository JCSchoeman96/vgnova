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
    return VgRuntimeMutationCore::safeStatusMetadata($metadata);
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
    $lockNames = VgRuntimeMutationCore::advisoryLockNames($target);
    if (($options['status'] ?? false) === true) {
        if (is_array($options['command']) || isset($options['purpose'])) {
            throw new VgRuntimeMutationException(71, 'INVALID_STATUS_ARGUMENTS', 'Status does not accept a mutation command or purpose.');
        }
        $filesystem = VgRuntimeMutationCore::probeFilesystemLock($lockFile);
        $databaseStatuses = VgRuntimeMutationDatabase::statusLockOwners($target, $resolvedRoot, $lockNames, $socketOverride);
        $databaseLocks = [];
        foreach (['a', 'b'] as $slot) {
            $databaseStatus = $databaseStatuses[$slot] ?? ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'DATABASE_STATUS_FAILED'];
            $databaseLocks[$slot] = [
                'name' => $lockNames[$slot],
                'state' => $databaseStatus['state'],
                'held' => match ($databaseStatus['state']) {
                    'held' => true,
                    'free' => false,
                    default => null,
                },
                'owner_connection_id' => $databaseStatus['owner_connection_id'],
                'error_code' => $databaseStatus['error_code'],
            ];
        }
        $response = [
            'target' => $target['id'],
            'lock_file' => $lockFile,
            'filesystem_lock_held' => $filesystem['held'],
            'database_locks' => $databaseLocks,
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
        static function (array $requestedLockNames, $hostLockHandle) use (&$lease, $target, $resolvedRoot, $socketOverride): array {
            $lease = VgRuntimeMutationDatabase::startKeepers($target, $resolvedRoot, $requestedLockNames, $hostLockHandle, $socketOverride);
            return $lease;
        },
        static function (array $registeredTarget, array $observedIdentity) use ($resolvedRoot): void {
            VgRuntimeMutationCore::validateTargetIdentity($registeredTarget, $observedIdentity, $resolvedRoot);
        },
        static function (array $proof, $lockHandle, array $lifetimeHandles, array $registrationHandles) use ($childArguments, $resolvedRoot, $socketOverride): int {
            $baseEnvironment = getenv() ?: [];
            $baseEnvironment['VGNOVA_MUTATION_WP_ROOT'] = $resolvedRoot;
            $environment = VgRuntimeMutationCore::childEnvironment($baseEnvironment, $proof, $socketOverride);
            return VgRuntimeMutationCore::runChildCommand($childArguments, $environment, $lockHandle, $lifetimeHandles, $registrationHandles);
        },
        static function (array $requestedLockNames) use (&$lease): void {
            if (is_array($lease)) {
                VgRuntimeMutationDatabase::releaseKeepers($lease, $requestedLockNames);
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
