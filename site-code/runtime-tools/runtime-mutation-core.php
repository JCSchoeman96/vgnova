<?php
declare(strict_types=1);

final class VgRuntimeMutationException extends RuntimeException
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $errorCode,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}

final class VgRuntimeMutationCore
{
    public const REGISTRY_SCHEMA_VERSION = 1;
    public const METADATA_SCHEMA_VERSION = 1;
    public const TARGET_ID = 'vgnova-local-10039';

    private const FORBIDDEN_OPTIONS = [
        '--force',
        '--force-unlock',
        '--steal',
        '--kill-owner',
        '--ignore-lock',
        '--ignore-db-lock',
        '--ignore-filesystem-lock',
        '--skip-session-check',
    ];

    private const SAFE_ENVIRONMENT_KEYS = [
        'PATH', 'HOME', 'USER', 'LOGNAME', 'TMPDIR', 'TMP', 'TEMP', 'TZ',
        'LANG', 'LC_ALL', 'LC_CTYPE', 'XDG_CONFIG_HOME', 'XDG_STATE_HOME',
        'WP_CLI_CONFIG_PATH', 'WP_CLI_PACKAGES_DIR',
    ];

    private const SESSION_ENVIRONMENT_KEYS = [
        'VGNOVA_MUTATION_SESSION_ID',
        'VGNOVA_MUTATION_TARGET',
        'VGNOVA_MUTATION_DB_LOCK',
        'VGNOVA_MUTATION_DB_CONNECTION_ID',
        'VGNOVA_MUTATION_LOCK_FILE',
    ];

    /** @return array{schema_version:int,targets:list<array<string,mixed>>} */
    public static function loadTargetRegistry(string $registryPath): array
    {
        if (! is_file($registryPath) || is_link($registryPath)) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET_REGISTRY', 'Target registry is missing or unsafe.');
        }

        $contents = file_get_contents($registryPath);
        if (! is_string($contents)) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET_REGISTRY', 'Target registry could not be read.');
        }

        try {
            $registry = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET_REGISTRY', 'Target registry JSON is invalid.');
        }

        if (! is_array($registry)
            || ($registry['schema_version'] ?? null) !== self::REGISTRY_SCHEMA_VERSION
            || ! is_array($registry['targets'] ?? null)
            || ! array_is_list($registry['targets'])) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET_REGISTRY', 'Target registry schema is unsupported.');
        }

        $seen = [];
        foreach ($registry['targets'] as $target) {
            self::validateTarget(is_array($target) ? $target : []);
            if (isset($seen[$target['id']])) {
                throw new VgRuntimeMutationException(71, 'INVALID_TARGET_REGISTRY', 'Target registry contains a duplicate target ID.');
            }
            $seen[$target['id']] = true;
        }

        return ['schema_version' => self::REGISTRY_SCHEMA_VERSION, 'targets' => $registry['targets']];
    }

    /** @param array<string,mixed> $registry @return array<string,mixed> */
    public static function resolveTarget(array $registry, string $targetId): array
    {
        foreach ($registry['targets'] ?? [] as $target) {
            if (is_array($target) && ($target['id'] ?? null) === $targetId) {
                return $target;
            }
        }

        throw new VgRuntimeMutationException(71, 'UNKNOWN_TARGET', 'Requested target is not registered.');
    }

    /** @param array<string,mixed> $target */
    public static function advisoryLockName(array $target): string
    {
        self::validateTarget($target);
        $identity = implode("\n", [
            (string) $target['id'],
            (string) $target['database_name'],
            (string) $target['home'],
            (string) $target['site'],
        ]);

        return 'vgnova.mutation.' . substr(hash('sha256', $identity), 0, 40);
    }

    /** @param array<string,string>|null $environment */
    public static function stateDirectory(?array $environment = null, array $prohibitedRoots = []): string
    {
        $environment ??= self::readEnvironment();
        $xdgStateHome = trim((string) ($environment['XDG_STATE_HOME'] ?? ''));
        if ($xdgStateHome !== '') {
            if (! self::isAbsolutePath($xdgStateHome)) {
                throw new VgRuntimeMutationException(71, 'INVALID_STATE_DIRECTORY', 'XDG_STATE_HOME must be absolute.');
            }

            $directory = rtrim($xdgStateHome, '/') . '/vgnova/runtime-mutation';
            self::validateStateDirectory($directory, $prohibitedRoots);
            return $directory;
        }

        $home = trim((string) ($environment['HOME'] ?? ''));
        if (! self::isAbsolutePath($home)) {
            throw new VgRuntimeMutationException(71, 'INVALID_STATE_DIRECTORY', 'HOME must be absolute when XDG_STATE_HOME is unset.');
        }

        $directory = rtrim($home, '/') . '/.local/state/vgnova/runtime-mutation';
        self::validateStateDirectory($directory, $prohibitedRoots);
        return $directory;
    }

    /** @return array{host:string,port:?int,socket:?string} */
    public static function parseDbHost(string $dbHost, ?string $socketOverride = null): array
    {
        $dbHost = trim($dbHost);
        if ($dbHost === '' || str_contains($dbHost, "\0")) {
            throw new VgRuntimeMutationException(71, 'INVALID_DB_HOST', 'DB_HOST is malformed.');
        }

        $host = $dbHost;
        $port = null;
        $socket = null;
        $separator = strpos($dbHost, ':');

        if ($separator !== false) {
            $host = substr($dbHost, 0, $separator);
            $suffix = substr($dbHost, $separator + 1);
            if ($suffix === '') {
                throw new VgRuntimeMutationException(71, 'INVALID_DB_HOST', 'DB_HOST is malformed.');
            }

            if (str_starts_with($suffix, '/')) {
                $socket = $suffix;
            } elseif (ctype_digit($suffix)) {
                $portValue = (int) $suffix;
                if ($portValue < 1 || $portValue > 65535) {
                    throw new VgRuntimeMutationException(71, 'INVALID_DB_HOST', 'DB_HOST port is outside the supported range.');
                }
                $port = $portValue;
            } else {
                throw new VgRuntimeMutationException(71, 'INVALID_DB_HOST', 'DB_HOST is malformed.');
            }
        }

        if ($host === '' || ! preg_match('/\A[A-Za-z0-9._-]+\z/', $host)) {
            throw new VgRuntimeMutationException(71, 'INVALID_DB_HOST', 'DB_HOST host name is malformed.');
        }

        if ($socketOverride !== null && $socketOverride !== '') {
            if (! self::isAbsolutePath($socketOverride) || str_contains($socketOverride, "\0")) {
                throw new VgRuntimeMutationException(71, 'INVALID_DB_SOCKET', 'Database socket path must be absolute.');
            }
            $socket = $socketOverride;
        }

        return ['host' => strtolower($host), 'port' => $socket === null ? $port : null, 'socket' => $socket];
    }

    /** @param array<string,mixed> $target */
    public static function validateTarget(array $target): void
    {
        $required = ['id', 'home', 'site', 'database_name', 'database_host', 'platform_core_entrypoint'];
        foreach ($required as $key) {
            if (! isset($target[$key]) || ! is_string($target[$key]) || trim($target[$key]) === '') {
                throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Target identity is incomplete.');
            }
        }

        if (! preg_match('/\A[a-z0-9][a-z0-9-]{0,62}\z/', $target['id'])) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Target ID is malformed.');
        }
        if (! preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}\z/', $target['database_name'])) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Database name is malformed.');
        }
        if (! preg_match('/\A[A-Za-z0-9._-]+\z/', $target['database_host'])) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Database host is malformed.');
        }

        foreach (['home', 'site'] as $urlKey) {
            $url = parse_url($target[$urlKey]);
            if (! is_array($url) || ! isset($url['scheme'], $url['host']) || ! in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
                throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Target URL is malformed.');
            }
        }

        $entrypoint = $target['platform_core_entrypoint'];
        if (str_starts_with($entrypoint, '/') || str_contains($entrypoint, "\\") || str_contains($entrypoint, "\0")) {
            throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Platform Core entrypoint must be a safe relative path.');
        }
        foreach (explode('/', $entrypoint) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new VgRuntimeMutationException(71, 'INVALID_TARGET', 'Platform Core entrypoint path is unsafe.');
            }
        }
    }

    /** @return resource|false */
    public static function acquireFilesystemLock(string $lockFile)
    {
        if (! self::isAbsolutePath($lockFile) || is_link($lockFile)) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock path is invalid.');
        }

        $directory = dirname($lockFile);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock directory could not be created.');
        }

        $previousMask = umask(0077);
        try {
            $handle = @fopen($lockFile, 'c+');
        } finally {
            umask($previousMask);
        }

        if (! is_resource($handle)) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock file could not be opened.');
        }
        if (is_link($lockFile)) {
            fclose($handle);
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock file must not be a symbolic link.');
        }

        @chmod($lockFile, 0600);
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        return $handle;
    }

    /** @return array{held:bool,metadata:?array} */
    public static function probeFilesystemLock(string $lockFile): array
    {
        if (! self::isAbsolutePath($lockFile) || is_link($lockFile)) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_PROBE_FAILED', 'Filesystem lock path is invalid.');
        }
        if (! file_exists($lockFile)) {
            return ['held' => false, 'metadata' => null];
        }

        $handle = @fopen($lockFile, 'r');
        if (! is_resource($handle)) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_PROBE_FAILED', 'Filesystem lock file could not be read.');
        }

        $metadata = self::readMetadataFromHandle($handle);
        $held = ! flock($handle, LOCK_EX | LOCK_NB);
        if (! $held) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return ['held' => $held, 'metadata' => $metadata];
    }

    /** @return array<string,mixed>|null */
    public static function readMetadata(string $lockFile): ?array
    {
        if (! is_file($lockFile) || is_link($lockFile)) {
            return null;
        }
        $handle = @fopen($lockFile, 'r');
        if (! is_resource($handle)) {
            return null;
        }
        $metadata = self::readMetadataFromHandle($handle);
        fclose($handle);
        return $metadata;
    }

    /** @param resource $handle @param array<string,mixed> $metadata */
    public static function writeMetadata($handle, array $metadata): void
    {
        if (! is_resource($handle)) {
            throw new VgRuntimeMutationException(70, 'METADATA_WRITE_FAILED', 'Lock metadata handle is unavailable.');
        }
        $serialized = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (! ftruncate($handle, 0) || ! rewind($handle)) {
            throw new VgRuntimeMutationException(70, 'METADATA_WRITE_FAILED', 'Lock metadata could not be reset.');
        }
        $offset = 0;
        $length = strlen($serialized);
        while ($offset < $length) {
            $written = fwrite($handle, substr($serialized, $offset));
            if ($written === false || $written === 0) {
                throw new VgRuntimeMutationException(70, 'METADATA_WRITE_FAILED', 'Lock metadata could not be written.');
            }
            $offset += $written;
        }
        if (! fflush($handle)) {
            throw new VgRuntimeMutationException(70, 'METADATA_WRITE_FAILED', 'Lock metadata could not be flushed.');
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function metadata(array $input): array
    {
        $command = is_array($input['command'] ?? null) ? $input['command'] : [];
        $target = is_string($input['target'] ?? null) ? $input['target'] : '';
        $purpose = is_string($input['purpose'] ?? null) ? $input['purpose'] : '';
        try {
            $safePurpose = self::validatePurpose($purpose);
        } catch (VgRuntimeMutationException) {
            $safePurpose = 'administrative mutation';
        }
        $summary = $command === [] ? 'command unavailable' : self::commandSummary($command);

        return [
            'schema_version' => self::METADATA_SCHEMA_VERSION,
            'target' => $target,
            'purpose' => $safePurpose,
            'pid' => getmypid() ?: 0,
            'hostname' => self::redact((string) gethostname()),
            'started_at_utc' => gmdate('c'),
            'repository_commit' => self::safeRevision($input['repository_commit'] ?? null),
            'working_directory' => self::safePath((string) ($input['working_directory'] ?? getcwd() ?: '')),
            'command_summary' => $summary,
            'state' => 'starting',
            'session_id' => null,
            'database_lock_name' => null,
            'database_connection_id' => null,
            'released_at_utc' => null,
            'child_exit_code' => null,
        ];
    }

    /** @param list<string> $argv */
    public static function commandSummary(array $argv): string
    {
        if ($argv === []) {
            return 'command unavailable';
        }
        $executable = basename((string) $argv[0]);
        if (! preg_match('/\A[A-Za-z0-9_.+-]{1,80}\z/', $executable)) {
            $executable = 'command';
        }
        return $executable . ' [' . count($argv) . ' arguments]';
    }

    /** @param array<string,string> $baseEnvironment @param array<string,mixed> $proof @return array<string,string> */
    public static function childEnvironment(array $baseEnvironment, array $proof): array
    {
        $environment = self::safeEnvironment($baseEnvironment);
        foreach (array_keys($environment) as $key) {
            if (str_starts_with($key, 'VGNOVA_MUTATION_')) {
                unset($environment[$key]);
            }
        }

        $environment['VGNOVA_MUTATION_SESSION_ID'] = (string) ($proof['session_id'] ?? '');
        $environment['VGNOVA_MUTATION_TARGET'] = (string) ($proof['target'] ?? '');
        $environment['VGNOVA_MUTATION_DB_LOCK'] = (string) ($proof['db_lock_name'] ?? '');
        $environment['VGNOVA_MUTATION_DB_CONNECTION_ID'] = (string) ($proof['db_connection_id'] ?? '');
        $environment['VGNOVA_MUTATION_LOCK_FILE'] = (string) ($proof['lock_file'] ?? '');
        return $environment;
    }

    /** @param array<string,string> $baseEnvironment @return array<string,string> */
    public static function safeEnvironment(array $baseEnvironment): array
    {
        $environment = [];
        foreach ($baseEnvironment as $key => $value) {
            if (! is_string($key) || ! is_string($value) || ! in_array($key, self::SAFE_ENVIRONMENT_KEYS, true)) {
                continue;
            }
            $environment[$key] = $value;
        }
        return $environment;
    }

    public static function validatePurpose(string $purpose): string
    {
        $purpose = trim($purpose);
        if ($purpose === '' || strlen($purpose) > 160 || preg_match('/\A[\p{L}\p{N}\p{Zs}\p{P}]+\z/u', $purpose) !== 1
            || preg_match('/\b(password|passwd|secret|token|api[_ -]?key|credential|mysql_pwd|database_url)\b\s*[:=]/i', $purpose) === 1
            || preg_match('~https?://[^\s]*[?&](?:password|passwd|secret|token|api[_-]?key)=~i', $purpose) === 1) {
            throw new VgRuntimeMutationException(71, 'INVALID_PURPOSE', 'Purpose must be a short, safe human-readable reason.');
        }
        return $purpose;
    }

    /** @param list<string> $command @param list<int> $passThroughDescriptors @return list<string> */
    public static function descriptorSafeCommand(array $command, array $passThroughDescriptors): array
    {
        if ($command === [] || ! array_is_list($command)) {
            throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Process command is invalid.');
        }
        foreach ($command as $argument) {
            if (! is_string($argument)) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Process arguments must be strings.');
            }
        }

        $preserve = [0 => true, 1 => true, 2 => true];
        foreach ($passThroughDescriptors as $descriptor) {
            if (! is_int($descriptor) || $descriptor < 3 || $descriptor > 255) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Process descriptor allowlist is invalid.');
            }
            $preserve[$descriptor] = true;
        }
        $preservePattern = implode('|', array_keys($preserve));
        $script = 'if [ ! -d /proc/self/fd ]; then exit 70; fi; '
            . 'for descriptor_path in /proc/self/fd/[0-9]*; do '
            . '[ -e "$descriptor_path" ] || continue; '
            . 'descriptor=${descriptor_path##*/}; '
            . 'case "$descriptor" in ' . $preservePattern . ') ;; '
            . '*) eval "exec ${descriptor}>&-" ;; esac; '
            . 'done; exec "$@"';

        return ['/bin/sh', '-c', $script, 'vgnova-fd-launcher', ...$command];
    }

    /** @param list<string> $arguments */
    public static function validateArguments(array $arguments): void
    {
        foreach ($arguments as $argument) {
            foreach (self::FORBIDDEN_OPTIONS as $forbidden) {
                if ($argument === $forbidden || str_starts_with($argument, $forbidden . '=')) {
                    throw new VgRuntimeMutationException(71, 'FORBIDDEN_COORDINATOR_OPTION', 'Coordinator option is not supported.');
                }
            }
        }
    }

    /** @param array<string,mixed> $target @param array<string,mixed> $observed */
    public static function validateTargetIdentity(array $target, array $observed, string $wpRoot): void
    {
        self::validateTarget($target);
        if (! self::isAbsolutePath($wpRoot) || ! is_dir($wpRoot)) {
            throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH', 'WordPress root is missing or invalid.');
        }

        $host = strtolower((string) ($observed['database_host'] ?? ''));
        if ($host !== strtolower((string) $target['database_host'])
            || (string) ($observed['database_name'] ?? '') !== (string) $target['database_name']
            || (string) ($observed['home'] ?? '') !== (string) $target['home']
            || (string) ($observed['site'] ?? '') !== (string) $target['site']) {
            throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH', 'Runtime identity does not match the registered target.');
        }

        $root = realpath($wpRoot);
        if (! is_string($root)) {
            throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH', 'WordPress root could not be resolved.');
        }
        $entrypoint = realpath($root . '/' . $target['platform_core_entrypoint']);
        if (! is_string($entrypoint) || ! is_file($entrypoint) || ! self::pathIsWithin($entrypoint, $root)) {
            throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH', 'Platform Core entrypoint is missing or outside the WordPress root.');
        }
    }

    /** @param array<string,string>|null $environment */
    public static function assertNotNested(?array $environment = null): void
    {
        $environment ??= self::readEnvironment();
        if (isset($environment['VGNOVA_MUTATION_SESSION_ID']) && $environment['VGNOVA_MUTATION_SESSION_ID'] !== '') {
            throw new VgRuntimeMutationException(75, 'NESTED_MUTATION_SESSION_FORBIDDEN', 'Nested mutation sessions are forbidden.');
        }
    }

    /**
     * Acquires the host lock, then starts the separate database lock keeper, validates identity, runs the child, and releases in reverse order.
     *
     * @param array<string,mixed> $target
     * @param array<string,mixed> $metadataInput
     * @param callable(string):array{result:mixed,connection_id:mixed,lifetime_handle?:resource,identity?:array<string,mixed>} $databaseAcquire
     * @param callable(array<string,mixed>,array<string,mixed>):void $identityValidator
     * @param callable(array<string,mixed>,resource,resource|null):int $childRunner
     * @param callable(string):void $databaseRelease
     * @param null|callable(array<string,mixed>):void $metadataObserver
     */
    public static function withSession(
        string $lockFile,
        array $target,
        array $metadataInput,
        callable $databaseAcquire,
        callable $identityValidator,
        callable $childRunner,
        callable $databaseRelease,
        ?callable $metadataObserver = null
    ): int {
        self::assertNotNested();
        $lockHandle = self::acquireFilesystemLock($lockFile);
        if (! is_resource($lockHandle)) {
            throw new VgRuntimeMutationException(72, 'FILESYSTEM_LOCK_CONTENTION', 'Another local mutation session holds the filesystem lock.');
        }

        $lockName = self::advisoryLockName($target);
        $databaseAcquired = false;
        $sessionId = null;
        $childExitCode = null;
        $failure = null;
        $baseMetadata = self::metadata(array_replace($metadataInput, ['target' => $target['id']]));
        $writeState = static function (string $state, array $changes = []) use (
            $lockHandle,
            $baseMetadata,
            $metadataObserver,
            &$sessionId,
            &$childExitCode
        ): void {
            $metadata = array_replace($baseMetadata, [
                'state' => $state,
                'session_id' => $sessionId,
                'child_exit_code' => $childExitCode,
                'released_at_utc' => $state === 'released' ? gmdate('c') : null,
            ], $changes);
            self::writeMetadata($lockHandle, $metadata);
            if ($metadataObserver !== null) {
                $metadataObserver($metadata);
            }
        };

        try {
            $databaseResult = $databaseAcquire($lockName);
            if (! is_array($databaseResult) || ! array_key_exists('result', $databaseResult)) {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_FAILED', 'Database lock acquisition failed.');
            }
            self::interpretGetLockResult($databaseResult['result']);
            $databaseAcquired = true;
            $connectionId = $databaseResult['connection_id'] ?? null;
            if (! is_scalar($connectionId) || (int) $connectionId < 1) {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_FAILED', 'Database connection identity is unavailable.');
            }

            $observedIdentity = is_array($databaseResult['identity'] ?? null) ? $databaseResult['identity'] : [];
            $identityValidator($target, $observedIdentity);
            $sessionId = bin2hex(random_bytes(16));
            $proof = [
                'session_id' => $sessionId,
                'target' => $target['id'],
                'db_lock_name' => $lockName,
                'db_connection_id' => (int) $connectionId,
                'lock_file' => $lockFile,
            ];
            $writeState('active', [
                'database_lock_name' => $lockName,
                'database_connection_id' => (int) $connectionId,
            ]);
            $lifetimeHandle = is_resource($databaseResult['lifetime_handle'] ?? null)
                ? $databaseResult['lifetime_handle']
                : null;
            $childExitCode = $childRunner($proof, $lockHandle, $lifetimeHandle);
            if (! is_int($childExitCode) || $childExitCode < 0 || $childExitCode > 255) {
                throw new VgRuntimeMutationException(70, 'INVALID_CHILD_EXIT_CODE', 'Child process returned an invalid exit code.');
            }
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            if ($databaseAcquired) {
                try {
                    $databaseRelease($lockName);
                } catch (Throwable $exception) {
                    $failure ??= new VgRuntimeMutationException(70, 'DATABASE_LOCK_RELEASE_FAILED', 'Database lock release failed.');
                }
            }

            try {
                $writeState('released', [
                    'database_lock_name' => $databaseAcquired ? $lockName : null,
                    'database_connection_id' => null,
                ]);
            } catch (Throwable $exception) {
                $failure ??= new VgRuntimeMutationException(70, 'METADATA_WRITE_FAILED', 'Released session metadata could not be written.');
            }

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        if ($failure !== null) {
            throw $failure;
        }

        return $childExitCode ?? 0;
    }

    public static function interpretGetLockResult(mixed $result): bool
    {
        if ($result === 1 || $result === '1') {
            return true;
        }
        if ($result === 0 || $result === '0') {
            throw new VgRuntimeMutationException(73, 'DATABASE_LOCK_CONTENTION', 'Another mutation session holds the database lock.');
        }
        throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_FAILED', 'Database lock acquisition returned an error.');
    }

    /**
     * Starts a child in a process group with exact argv boundaries. Descriptor 3 carries the flock;
     * descriptor 4 carries the database keeper's child-lifetime pipe.
     *
     * @param list<string> $arguments
     * @param array<string,string> $environment
     * @param resource $lockHandle
     * @param resource|null $lifetimeHandle
     */
    public static function runChildCommand(array $arguments, array $environment, $lockHandle, $lifetimeHandle = null): int
    {
        if (PHP_SAPI !== 'cli' || $arguments === [] || ! array_is_list($arguments) || ! is_resource($lockHandle)
            || ($lifetimeHandle !== null && ! is_resource($lifetimeHandle))) {
            throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child process configuration is invalid.');
        }
        foreach ($arguments as $argument) {
            if (! is_string($argument)) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child arguments must be strings.');
            }
        }
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')
            || ! function_exists('pcntl_exec') || ! function_exists('posix_setsid')
            || ! function_exists('posix_kill') || ! function_exists('proc_open')) {
            throw new VgRuntimeMutationException(70, 'SIGNAL_SUPPORT_UNAVAILABLE', 'Signal-safe process support is unavailable.');
        }

        $previousAsync = pcntl_async_signals(true);
        $previousInterrupt = pcntl_signal_get_handler(SIGINT);
        $previousTerminate = pcntl_signal_get_handler(SIGTERM);
        $process = null;
        $pipes = [];
        $childPid = null;
        $groupReady = false;
        $signalState = ['signal' => null, 'sent_at' => null];
        $handler = static function (int $signal) use (&$signalState, &$process, &$childPid, &$groupReady): void {
            if ($signalState['signal'] === null) {
                $signalState['signal'] = $signal;
                $signalState['sent_at'] = microtime(true);
            } else {
                $signal = SIGKILL;
            }
            if (is_resource($process)) {
                if ($groupReady && is_int($childPid)) {
                    @posix_kill(-$childPid, $signal);
                } else {
                    @proc_terminate($process, $signal);
                }
            }
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);

        try {
            if ($signalState['signal'] !== null) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_INTERRUPTED', 'Child start was interrupted by a signal.');
            }
            $descriptors = [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
                3 => $lockHandle,
                5 => ['pipe', 'w'],
            ];
            if (is_resource($lifetimeHandle)) {
                $descriptors[4] = $lifetimeHandle;
            }
            $runner = __DIR__ . '/runtime-mutation-exec.php';
            $childEnvironment = self::safeEnvironment($environment);
            foreach (self::SESSION_ENVIRONMENT_KEYS as $sessionKey) {
                if (isset($environment[$sessionKey]) && is_string($environment[$sessionKey])) {
                    $childEnvironment[$sessionKey] = $environment[$sessionKey];
                }
            }
            $processCommand = self::descriptorSafeCommand(
                [PHP_BINARY, $runner, ...$arguments],
                is_resource($lifetimeHandle) ? [3, 4, 5] : [3, 5]
            );
            $process = @proc_open($processCommand, $descriptors, $pipes, null, $childEnvironment);
            if (! is_resource($process)) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child process could not be started.');
            }

            $status = proc_get_status($process);
            $childPid = is_int($status['pid'] ?? null) ? $status['pid'] : null;
            if (is_resource($lifetimeHandle)) {
                fclose($lifetimeHandle);
            }
            $ready = isset($pipes[5]) && is_resource($pipes[5]) ? fgets($pipes[5]) : false;
            if (isset($pipes[5]) && is_resource($pipes[5])) {
                fclose($pipes[5]);
            }
            unset($pipes[5]);
            if ($ready !== "ready\n" || ! is_int($childPid) || $childPid < 1) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child process group could not be established.');
            }
            $groupReady = true;
            if ($signalState['signal'] !== null) {
                @posix_kill(-$childPid, $signalState['signal']);
            }

            $observedExitCode = null;
            $observedSignal = null;
            while (true) {
                $status = proc_get_status($process);
                if (! $status['running']) {
                    if (is_int($status['exitcode']) && $status['exitcode'] >= 0) {
                        $observedExitCode = $status['exitcode'];
                    }
                    if (($status['signaled'] ?? false) && is_int($status['termsig'] ?? null)) {
                        $observedSignal = $status['termsig'];
                    }
                    break;
                }

                if ($signalState['signal'] !== null
                    && is_float($signalState['sent_at'])
                    && microtime(true) - $signalState['sent_at'] >= 1.0) {
                    @posix_kill(-$childPid, SIGKILL);
                }
                usleep(50000);
            }

            $closedExitCode = proc_close($process);
            $process = null;
            self::terminateProcessGroupRemainder($childPid);
            if ($signalState['signal'] !== null) {
                return 128 + $signalState['signal'];
            }
            if ($observedSignal !== null) {
                return 128 + $observedSignal;
            }
            if ($observedExitCode !== null) {
                return $observedExitCode;
            }
            if ($closedExitCode >= 0) {
                return $closedExitCode;
            }

            throw new VgRuntimeMutationException(70, 'CHILD_EXIT_UNKNOWN', 'Child process exit status is unavailable.');
        } catch (VgRuntimeMutationException $exception) {
            if (is_resource($process)) {
                if (is_int($childPid) && $groupReady) {
                    @posix_kill(-$childPid, SIGTERM);
                } else {
                    @proc_terminate($process, SIGTERM);
                }
                usleep(100000);
                if (is_int($childPid) && $groupReady) {
                    @posix_kill(-$childPid, SIGKILL);
                } else {
                    @proc_terminate($process, SIGKILL);
                }
                while (proc_get_status($process)['running']) {
                    usleep(50000);
                }
                proc_close($process);
                $process = null;
            }
            if (is_int($childPid) && $groupReady) {
                self::terminateProcessGroupRemainder($childPid);
            }
            throw $exception;
        } catch (Throwable) {
            if (is_resource($process)) {
                if (is_int($childPid) && $groupReady) {
                    @posix_kill(-$childPid, SIGTERM);
                } else {
                    @proc_terminate($process, SIGTERM);
                }
                usleep(100000);
                if (is_int($childPid) && $groupReady) {
                    @posix_kill(-$childPid, SIGKILL);
                } else {
                    @proc_terminate($process, SIGKILL);
                }
                while (proc_get_status($process)['running']) {
                    usleep(50000);
                }
                proc_close($process);
                $process = null;
            }
            if (is_int($childPid) && $groupReady) {
                self::terminateProcessGroupRemainder($childPid);
            }
            throw new VgRuntimeMutationException(70, 'CHILD_WAIT_FAILED', 'The coordinator could not wait for the child process.');
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            pcntl_signal(SIGINT, $previousInterrupt);
            pcntl_signal(SIGTERM, $previousTerminate);
            pcntl_async_signals($previousAsync);
        }
    }

    private static function terminateProcessGroupRemainder(int $processGroupId): void
    {
        if ($processGroupId < 2) {
            return;
        }
        if (@posix_kill(-$processGroupId, 0)) {
            @posix_kill(-$processGroupId, SIGTERM);
            $deadline = microtime(true) + 1.0;
            while (@posix_kill(-$processGroupId, 0) && microtime(true) < $deadline) {
                usleep(20000);
            }
            if (@posix_kill(-$processGroupId, 0)) {
                @posix_kill(-$processGroupId, SIGKILL);
            }
            while (@posix_kill(-$processGroupId, 0)) {
                usleep(20000);
            }
        }
    }

    /** @return array<string,string> */
    private static function readEnvironment(): array
    {
        $environment = getenv();
        if (! is_array($environment)) {
            $environment = [];
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $environment[$key] = $value;
            }
        }
        return $environment;
    }

    /** @param list<string> $prohibitedRoots */
    private static function validateStateDirectory(string $directory, array $prohibitedRoots): void
    {
        if (! self::isAbsolutePath($directory)
            || in_array('.', explode('/', $directory), true)
            || in_array('..', explode('/', $directory), true)) {
            throw new VgRuntimeMutationException(71, 'INVALID_STATE_DIRECTORY', 'Mutation state directory path is unsafe.');
        }

        $roots = [sys_get_temp_dir(), '/tmp', '/var/tmp', dirname(__DIR__, 2), ...$prohibitedRoots];
        foreach ($roots as $root) {
            if (! is_string($root) || ! self::isAbsolutePath($root)) {
                continue;
            }
            $canonicalRoot = realpath($root);
            if (! is_string($canonicalRoot)) {
                $canonicalRoot = rtrim($root, '/');
            }
            if ($directory === $canonicalRoot || self::pathIsWithin($directory, $canonicalRoot)) {
                throw new VgRuntimeMutationException(71, 'INVALID_STATE_DIRECTORY', 'Mutation state directory is under a prohibited runtime or temporary path.');
            }
        }

        $existingAncestor = $directory;
        while (! file_exists($existingAncestor)) {
            $parent = dirname($existingAncestor);
            if ($parent === $existingAncestor) {
                break;
            }
            $existingAncestor = $parent;
        }
        $canonicalAncestor = realpath($existingAncestor);
        if (is_string($canonicalAncestor)) {
            foreach ([sys_get_temp_dir(), '/tmp', '/var/tmp', dirname(__DIR__, 2), ...$prohibitedRoots] as $root) {
                if (! is_string($root) || ! self::isAbsolutePath($root)) {
                    continue;
                }
                $canonicalRoot = realpath($root);
                $canonicalRoot = is_string($canonicalRoot) ? $canonicalRoot : rtrim($root, '/');
                if ($canonicalAncestor === $canonicalRoot || self::pathIsWithin($canonicalAncestor, $canonicalRoot)) {
                    throw new VgRuntimeMutationException(71, 'INVALID_STATE_DIRECTORY', 'Mutation state directory resolves under a prohibited runtime or temporary path.');
                }
            }
        }
    }

    /** @param resource $handle @return array<string,mixed>|null */
    private static function readMetadataFromHandle($handle): ?array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if (! is_string($contents) || trim($contents) === '') {
            return null;
        }
        try {
            $metadata = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        return is_array($metadata) ? $metadata : null;
    }

    public static function isAbsolutePathForDatabase(string $path): bool
    {
        return self::isAbsolutePath($path);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/');
    }

    private static function pathIsWithin(string $path, string $root): bool
    {
        $normalizedRoot = rtrim($root, '/') . '/';
        return str_starts_with($path, $normalizedRoot);
    }

    private static function redact(string $value): string
    {
        $value = preg_replace('/\b(password|passwd|secret|token|api[_-]?key|credential|salt)\b\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $value) ?? '[redacted]';
        return mb_substr($value, 0, 240);
    }

    private static function safePath(string $path): string
    {
        return self::redact($path);
    }

    private static function safeRevision(mixed $revision): ?string
    {
        if (! is_string($revision) || ! preg_match('/\A[a-f0-9]{7,40}\z/i', $revision)) {
            return null;
        }
        return strtolower($revision);
    }
}
