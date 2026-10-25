<?php
declare(strict_types=1);

final class VgRuntimeMutationException extends RuntimeException
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $errorCode,
        string $message = '',
        public readonly bool $retainHostLock = false
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
        'WP_CLI_CONFIG_PATH', 'WP_CLI_PACKAGES_DIR', 'VGNOVA_MUTATION_WP_ROOT',
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
        $directoryIdentity = self::validateLockDirectory($directory);

        if (file_exists($lockFile)) {
            $pathStat = @lstat($lockFile);
            if (! is_array($pathStat)
                || ($pathStat['mode'] & 0170000) !== 0100000
                || ($pathStat['uid'] ?? null) !== posix_geteuid()
                || (($pathStat['mode'] & 0077) !== 0)) {
                throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Existing lock file ownership or permissions are unsafe.');
            }
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
        if (! self::lockDirectoryMatches($directory, $directoryIdentity)
            || ! self::lockPathMatchesHandle($lockFile, $handle)) {
            fclose($handle);
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock path changed while it was opened.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        if (! self::lockDirectoryMatches($directory, $directoryIdentity)
            || ! self::lockPathMatchesHandle($lockFile, $handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock path changed during acquisition.');
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

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    public static function safeStatusMetadata(array $metadata): array
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
            if (! is_string($value) && ! is_int($value) && $value !== null) {
                continue;
            }
            if ($key === 'purpose' && is_string($value)) {
                try {
                    $value = self::validatePurpose($value);
                } catch (VgRuntimeMutationException) {
                    $value = 'administrative mutation';
                }
            } elseif ($key === 'command_summary' && is_string($value)
                && preg_match('/\\A[A-Za-z0-9_.+-]{1,80} \\[[0-9]{1,6} arguments\\]\\z/', $value) !== 1
                && $value !== 'command unavailable') {
                $value = 'command unavailable';
            }
            $safe[$key] = $value;
        }

        return $safe;
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
            if (str_starts_with($key, 'VGNOVA_MUTATION_') && $key !== 'VGNOVA_MUTATION_WP_ROOT') {
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
            || preg_match('/(?:^|[^A-Za-z0-9])[A-Za-z0-9_-]*(?:password|passwd|pwd|secret|token|api[_ -]?key|auth[_ -]?key|credential|mysql_pwd|database_url|salt)/i', $purpose) === 1
            || preg_match('/\b[A-Za-z0-9_-]{32,}\b/', $purpose) === 1
            || preg_match('~https?://[^\s]*[?&](?:password|passwd|secret|token|api[_-]?key)=~i', $purpose) === 1
            || preg_match('~\b[a-z][a-z0-9+.-]*://[^/\s?#@]+:[^/\s?#@]*@~i', $purpose) === 1) {
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
            . '*) exec {descriptor}>&- ;; esac; '
            . 'done; exec "$@"';

        return ['/bin/bash', '-c', $script, 'vgnova-fd-launcher', ...$command];
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
     * @param callable(string,resource):array{result:mixed,connection_id:mixed,lifetime_handle?:resource,registration_handle?:resource,identity?:array<string,mixed>} $databaseAcquire
     * @param callable(array<string,mixed>,array<string,mixed>):void $identityValidator
     * @param callable(array<string,mixed>,resource,resource|null,resource|null):int $childRunner
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
        $cleanupFailure = null;
        $retainHostLock = false;
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
            $databaseResult = $databaseAcquire($lockName, $lockHandle);
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
            $registrationHandle = is_resource($databaseResult['registration_handle'] ?? null)
                ? $databaseResult['registration_handle']
                : null;
            $childResult = $childRunner($proof, $lockHandle, $lifetimeHandle, $registrationHandle);
            if (! is_int($childResult) || $childResult < 0 || $childResult > 255) {
                throw new VgRuntimeMutationException(70, 'INVALID_CHILD_EXIT_CODE', 'Child process returned an invalid exit code.');
            }
            $childExitCode = $childResult;
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            if ($databaseAcquired) {
                try {
                    $databaseRelease($lockName);
                } catch (Throwable $exception) {
                    $cleanupFailure = $exception instanceof VgRuntimeMutationException
                        ? $exception
                        : new VgRuntimeMutationException(70, 'DATABASE_LOCK_RELEASE_FAILED', 'Database lock release failed.');
                    $retainHostLock = $cleanupFailure->retainHostLock;
                }
            }

            try {
                $writeState($cleanupFailure === null ? 'released' : 'cleanup_failed', [
                    'database_lock_name' => $databaseAcquired ? $lockName : null,
                    'database_connection_id' => null,
                    'cleanup_error_code' => $cleanupFailure instanceof VgRuntimeMutationException
                        ? $cleanupFailure->errorCode
                        : null,
                ]);
            } catch (Throwable $exception) {
                $cleanupFailure ??= new VgRuntimeMutationException(70, 'METADATA_WRITE_FAILED', 'Released session metadata could not be written.');
            }

            if (! $retainHostLock) {
                flock($lockHandle, LOCK_UN);
            }
            fclose($lockHandle);
        }

        if ($childExitCode !== null) {
            if ($cleanupFailure !== null) {
                $cleanupErrorCode = $cleanupFailure instanceof VgRuntimeMutationException
                    ? $cleanupFailure->errorCode
                    : 'SESSION_CLEANUP_FAILED';
                fwrite(STDERR, 'Runtime mutation cleanup failed after child exit: ' . $cleanupErrorCode . ".\n");
            }
            return $childExitCode;
        }

        if ($cleanupFailure !== null) {
            $failure ??= $cleanupFailure;
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

    /** @param list<int>|null $descendants @return 'empty'|'present'|'unknown' */
    public static function classifyDescendantScan(?array $descendants): string
    {
        if (! is_array($descendants)) {
            return 'unknown';
        }

        return $descendants === [] ? 'empty' : 'present';
    }

    /**
     * Starts a child in a process group with exact argv boundaries. Descriptor 3 carries the flock;
     * descriptor 4 carries the database keeper's child-lifetime pipe. Descriptor 6 gates exec until
     * the keeper has registered the child's process group.
     *
     * @param list<string> $arguments
     * @param array<string,string> $environment
     * @param resource $lockHandle
     * @param resource|null $lifetimeHandle
     * @param resource|null $registrationHandle
     */
    public static function runChildCommand(array $arguments, array $environment, $lockHandle, $lifetimeHandle = null, $registrationHandle = null): int
    {
        if (PHP_SAPI !== 'cli' || $arguments === [] || ! array_is_list($arguments) || ! is_resource($lockHandle)
            || ($lifetimeHandle !== null && ! is_resource($lifetimeHandle))
            || ($registrationHandle !== null && ! is_resource($registrationHandle))) {
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
        $supervisorPid = null;
        $childPid = null;
        $groupReady = false;
        $signalState = ['signal' => null, 'sent_at' => null];
        $handler = static function (int $signal) use (&$signalState, &$process, &$supervisorPid, &$childPid, &$groupReady): void {
            if ($signalState['signal'] === null) {
                $signalState['signal'] = $signal;
                $signalState['sent_at'] = microtime(true);
            } else {
                $signal = SIGKILL;
            }
            if (is_resource($process)) {
                if ($groupReady && is_int($childPid)) {
                    @posix_kill(-$childPid, $signal);
                    if (is_int($supervisorPid) && $supervisorPid > 1) {
                        @posix_kill($supervisorPid, SIGTERM);
                    }
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
                6 => ['pipe', 'r'],
                7 => ['pipe', 'w'],
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
            $runnerArguments = [PHP_BINARY, $runner];
            if (is_resource($lifetimeHandle)) {
                $runnerArguments[] = '--completion-channel';
            }
            $processCommand = self::descriptorSafeCommand(
                [...$runnerArguments, ...$arguments],
                is_resource($lifetimeHandle) ? [3, 4, 5, 6, 7] : [3, 5, 6, 7]
            );
            $process = @proc_open($processCommand, $descriptors, $pipes, null, $childEnvironment);
            if (! is_resource($process)) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child process could not be started.');
            }

            $status = proc_get_status($process);
            $supervisorPid = is_int($status['pid'] ?? null) ? $status['pid'] : null;
            if (is_resource($lifetimeHandle)) {
                fclose($lifetimeHandle);
            }
            $ready = false;
            if (isset($pipes[5]) && is_resource($pipes[5])) {
                stream_set_blocking($pipes[5], false);
                $readyBuffer = '';
                $readyDeadline = microtime(true) + 5.0;
                while (microtime(true) < $readyDeadline) {
                    $readinessPoll = self::pollChildReadiness(
                        $pipes[5],
                        static fn (): bool => $signalState['signal'] !== null
                    );
                    if ($readinessPoll['state'] === 'interrupted') {
                        if (self::terminateDirectProcess($process, null, false)) {
                            proc_close($process);
                            $process = null;
                        }
                        throw new VgRuntimeMutationException(70, 'CHILD_START_INTERRUPTED', 'Child start was interrupted by a signal.');
                    }
                    if ($readinessPoll['state'] === 'data') {
                        $readyBuffer .= $readinessPoll['chunk'];
                    }
                    if (strlen($readyBuffer) > 128) {
                        break;
                    }
                    if (str_contains($readyBuffer, "\n")) {
                        $ready = $readyBuffer;
                        break;
                    }
                    if ($readinessPoll['state'] === 'eof') {
                        break;
                    }
                    $status = proc_get_status($process);
                    if (! ($status['running'] ?? false)) {
                        break;
                    }
                }
            }
            if (isset($pipes[5]) && is_resource($pipes[5])) {
                fclose($pipes[5]);
            }
            unset($pipes[5]);
            if (! is_string($ready)
                || preg_match('/\\Aready:([1-9][0-9]{0,9})\\n\\z/', $ready, $readyMatches) !== 1
                || ! is_int($supervisorPid)
                || $supervisorPid < 1) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child process group could not be established.');
            }
            $childPid = (int) $readyMatches[1];
            $groupReady = true;
            if (is_resource($registrationHandle)) {
                $registration = (int) $supervisorPid . "\n";
                if (fwrite($registrationHandle, $registration) !== strlen($registration) || ! fflush($registrationHandle)) {
                    throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED', 'Child process group could not be registered with the database lock keeper.');
                }
                fclose($registrationHandle);
            }
            if (! isset($pipes[6]) || ! is_resource($pipes[6]) || fwrite($pipes[6], "go\n") !== 3 || ! fflush($pipes[6])) {
                throw new VgRuntimeMutationException(70, 'CHILD_START_FAILED', 'Child command could not pass its start gate.');
            }
            fclose($pipes[6]);
            unset($pipes[6]);
            if ($signalState['signal'] !== null) {
                @posix_kill(-$childPid, $signalState['signal']);
                if (is_int($supervisorPid) && $supervisorPid > 1) {
                    @posix_kill($supervisorPid, SIGTERM);
                }
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

            $statusLine = isset($pipes[7]) && is_resource($pipes[7])
                ? stream_get_contents($pipes[7], 256)
                : false;
            if (isset($pipes[7]) && is_resource($pipes[7])) {
                fclose($pipes[7]);
            }
            unset($pipes[7]);
            if (is_string($statusLine)) {
                try {
                    $childStatus = json_decode(trim($statusLine), true, 8, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $childStatus = null;
                }
                if (is_array($childStatus) && ($childStatus['kind'] ?? null) === 'exit'
                    && is_int($childStatus['exit_code'] ?? null)
                    && $childStatus['exit_code'] >= 0 && $childStatus['exit_code'] <= 255) {
                    $observedExitCode = $childStatus['exit_code'];
                    $observedSignal = null;
                } elseif (is_array($childStatus) && ($childStatus['kind'] ?? null) === 'signal'
                    && is_int($childStatus['signal'] ?? null)
                    && $childStatus['signal'] > 0 && $childStatus['signal'] < 128) {
                    $observedExitCode = null;
                    $observedSignal = $childStatus['signal'];
                }
            }

            $closedExitCode = proc_close($process);
            $process = null;
            if ($observedExitCode !== null) {
                return $observedExitCode;
            }
            if ($observedSignal !== null) {
                return 128 + ($signalState['signal'] ?? $observedSignal);
            }
            if ($closedExitCode >= 0) {
                return $closedExitCode;
            }
            if ($signalState['signal'] !== null) {
                return 128 + $signalState['signal'];
            }

            throw new VgRuntimeMutationException(70, 'CHILD_EXIT_UNKNOWN', 'Child process exit status is unavailable.');
        } catch (VgRuntimeMutationException $exception) {
            if (is_resource($process)) {
                if (self::terminateDirectProcess($process, $childPid, $groupReady)) {
                    proc_close($process);
                    $process = null;
                }
            }
            if (is_int($childPid) && $groupReady) {
                self::terminateProcessGroupRemainder($childPid);
            }
            throw $exception;
        } catch (Throwable) {
            if (is_resource($process)) {
                if (self::terminateDirectProcess($process, $childPid, $groupReady)) {
                    proc_close($process);
                    $process = null;
                }
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

    public static function waitForProcessGroupExit(int $processGroupId, float $timeoutSeconds): bool
    {
        if ($processGroupId < 2) {
            return false;
        }
        if (! is_finite($timeoutSeconds) || $timeoutSeconds < 0.0) {
            return false;
        }
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            if (! self::processGroupHasLiveProcesses($processGroupId)) {
                return true;
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(20000);
        } while (true);

        return ! self::processGroupHasLiveProcesses($processGroupId);
    }

    private static function processGroupHasLiveProcesses(int $processGroupId): bool
    {
        $statFiles = @glob('/proc/[0-9]*/stat');
        if (is_array($statFiles)) {
            foreach ($statFiles as $statFile) {
                $stat = @file_get_contents($statFile);
                if (! is_string($stat)) {
                    continue;
                }
                $closingParenthesis = strrpos($stat, ')');
                if (! is_int($closingParenthesis)) {
                    continue;
                }
                $fields = preg_split('/\s+/', trim(substr($stat, $closingParenthesis + 1)));
                if (! is_array($fields) || ! isset($fields[0], $fields[2])
                    || ! ctype_digit($fields[2]) || (int) $fields[2] !== $processGroupId) {
                    continue;
                }
                if (! in_array($fields[0], ['Z', 'X'], true)) {
                    return true;
                }
            }
            return false;
        }

        return @posix_kill(-$processGroupId, 0);
    }

    private static function terminateProcessGroupRemainder(int $processGroupId): bool
    {
        if ($processGroupId < 2 || self::waitForProcessGroupExit($processGroupId, 0.0)) {
            return true;
        }
        @posix_kill(-$processGroupId, SIGTERM);
        if (self::waitForProcessGroupExit($processGroupId, 1.0)) {
            return true;
        }
        @posix_kill(-$processGroupId, SIGKILL);
        return self::waitForProcessGroupExit($processGroupId, 2.0);
    }

    /** @param resource $stream @return array{state:string,chunk:string} */
    private static function pollChildReadiness($stream, callable $signalPending): array
    {
        if ($signalPending()) {
            return ['state' => 'interrupted', 'chunk' => ''];
        }
        if (! is_resource($stream)) {
            return ['state' => 'eof', 'chunk' => ''];
        }
        $read = [$stream];
        $write = null;
        $except = null;
        $selected = @stream_select($read, $write, $except, 0, 50000);
        if ($selected === false || $selected === 0) {
            return ['state' => 'waiting', 'chunk' => ''];
        }
        $chunk = @fread($stream, 128);
        if (! is_string($chunk)) {
            return ['state' => 'eof', 'chunk' => ''];
        }
        if ($chunk === '' && feof($stream)) {
            return ['state' => 'eof', 'chunk' => ''];
        }

        return ['state' => 'data', 'chunk' => $chunk];
    }

    /** @param resource $process */
    private static function terminateDirectProcess($process, ?int $processGroupId, bool $groupReady): bool
    {
        if ($groupReady && is_int($processGroupId) && $processGroupId > 1) {
            @posix_kill(-$processGroupId, SIGTERM);
            $status = proc_get_status($process);
            $supervisorPid = is_int($status['pid'] ?? null) ? $status['pid'] : null;
            if (is_int($supervisorPid) && $supervisorPid > 1) {
                @posix_kill($supervisorPid, SIGTERM);
            }
            return self::waitForProcessHandleExit($process, 3.5);
        } else {
            @proc_terminate($process, SIGTERM);
        }
        if (self::waitForProcessHandleExit($process, 0.1)) {
            return true;
        }
        if ($groupReady && is_int($processGroupId) && $processGroupId > 1) {
            @posix_kill(-$processGroupId, SIGKILL);
        } else {
            @proc_terminate($process, SIGKILL);
        }
        return self::waitForProcessHandleExit($process, 2.0);
    }

    /** @param resource $process */
    private static function waitForProcessHandleExit($process, float $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $status = proc_get_status($process);
            if (! ($status['running'] ?? false)) {
                return true;
            }
            if (microtime(true) >= $deadline) {
                return false;
            }
            usleep(20000);
        } while (true);
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

    /** @return array{dev:int,ino:int,uid:int,mode:int} */
    private static function validateLockDirectory(string $directory): array
    {
        if (is_link($directory) || ! function_exists('posix_geteuid')) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock directory cannot be verified safely.');
        }
        $stat = @lstat($directory);
        if (! is_array($stat)
            || ($stat['mode'] & 0170000) !== 0040000
            || ($stat['uid'] ?? null) !== posix_geteuid()
            || (($stat['mode'] & 0077) !== 0)
            || realpath($directory) !== rtrim($directory, '/')) {
            throw new VgRuntimeMutationException(70, 'FILESYSTEM_LOCK_OPEN_FAILED', 'Filesystem lock directory ownership or permissions are unsafe.');
        }

        return [
            'dev' => (int) $stat['dev'],
            'ino' => (int) $stat['ino'],
            'uid' => (int) $stat['uid'],
            'mode' => (int) $stat['mode'],
        ];
    }

    /** @param array{dev:int,ino:int,uid:int,mode:int} $identity */
    private static function lockDirectoryMatches(string $directory, array $identity): bool
    {
        if (is_link($directory) || realpath($directory) !== rtrim($directory, '/')) {
            return false;
        }
        $stat = @lstat($directory);
        return is_array($stat)
            && ($stat['mode'] & 0170000) === 0040000
            && ($stat['uid'] ?? null) === $identity['uid']
            && (($stat['mode'] & 0077) === 0)
            && ($stat['dev'] ?? null) === $identity['dev']
            && ($stat['ino'] ?? null) === $identity['ino'];
    }

    /** @param resource $handle */
    private static function lockPathMatchesHandle(string $lockFile, $handle): bool
    {
        if (is_link($lockFile)) {
            return false;
        }
        $pathStat = @lstat($lockFile);
        $handleStat = @fstat($handle);
        if (! is_array($pathStat) || ! is_array($handleStat)) {
            return false;
        }
        return ($pathStat['mode'] & 0170000) === 0100000
            && ($pathStat['uid'] ?? null) === posix_geteuid()
            && (($pathStat['mode'] & 0077) === 0)
            && ($pathStat['dev'] ?? null) === ($handleStat['dev'] ?? null)
            && ($pathStat['ino'] ?? null) === ($handleStat['ino'] ?? null);
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
