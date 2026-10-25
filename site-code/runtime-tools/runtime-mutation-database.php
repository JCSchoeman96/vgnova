<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime-mutation-core.php';

final class VgRuntimeMutationDatabase
{
    /**
     * Start a separate process that owns the database advisory lock until its lifetime pipe reaches EOF.
     *
     * @param array<string,mixed> $target
     * @param resource $hostLockHandle
     * @return array<string,mixed>
     */
    public static function startKeeper(
        array $target,
        string $wpRoot,
        string $lockName,
        $hostLockHandle,
        ?string $socketOverride = null,
        ?array $environment = null
    ): array {
        if (! is_resource($hostLockHandle)) {
            throw new VgRuntimeMutationException(70, 'HOST_LOCK_HANDLE_UNAVAILABLE', 'The host lock handle is unavailable to the database lock keeper.');
        }
        if (! VgRuntimeMutationCore::isAbsolutePathForDatabase($wpRoot) || ! is_dir($wpRoot)) {
            throw new VgRuntimeMutationException(71, 'INVALID_WORDPRESS_ROOT', 'WordPress root must be an existing absolute path.');
        }
        if ($lockName !== VgRuntimeMutationCore::advisoryLockName($target)) {
            throw new VgRuntimeMutationException(71, 'INVALID_DATABASE_LOCK_NAME', 'Database lock name does not match the registered target.');
        }

        $helper = __DIR__ . '/runtime-mutation-db-lock-keeper.php';
        $arguments = [
            PHP_BINARY,
            $helper,
            '--target=' . $target['id'],
            '--wp-root=' . $wpRoot,
            '--lock-name=' . $lockName,
            '--db-socket=' . ($socketOverride ?? ''),
        ];
        $command = VgRuntimeMutationCore::descriptorSafeCommand($arguments, [3, 4]);
        $environment ??= self::readEnvironment();
        $environment = VgRuntimeMutationCore::safeEnvironment($environment);
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'w'],
            3 => $hostLockHandle,
            4 => ['pipe', 'r'],
        ], $pipes, null, $environment);
        if (! is_resource($process)) {
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_START_FAILED', 'Database lock keeper could not be started.');
        }

        stream_set_timeout($pipes[1], 30);
        $readyLine = fgets($pipes[1]);
        $readyTimedOut = (bool) (stream_get_meta_data($pipes[1])['timed_out'] ?? false);
        $ready = null;
        if (! $readyTimedOut && is_string($readyLine)) {
            try {
                $decoded = json_decode($readyLine, true, 16, JSON_THROW_ON_ERROR);
                $ready = is_array($decoded) ? $decoded : null;
            } catch (JsonException) {
                $ready = null;
            }
        }

        if (! is_array($ready) || ($ready['ok'] ?? false) !== true) {
            @proc_terminate($process, SIGKILL);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[4]);
            $status = proc_close($process);
            $exitCode = is_array($ready) && is_int($ready['exit_code'] ?? null)
                ? $ready['exit_code']
                : 70;
            $errorCode = is_array($ready) && is_string($ready['error_code'] ?? null)
                ? $ready['error_code']
                : 'DATABASE_LOCK_KEEPER_FAILED';
            $message = match ($exitCode) {
                71 => 'Database configuration or target is invalid.',
                73 => 'Another mutation session holds the database lock.',
                74 => 'Runtime identity does not match the registered target.',
                default => 'Database lock keeper could not establish a session.',
            };
            throw new VgRuntimeMutationException($exitCode, $errorCode, $message);
        }

        $connectionId = $ready['connection_id'] ?? null;
        $identity = $ready['identity'] ?? null;
        if (! is_scalar($connectionId) || (int) $connectionId < 1 || ! is_array($identity)) {
            @proc_terminate($process, SIGKILL);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[4]);
            proc_close($process);
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED', 'Database lock keeper returned an invalid proof.');
        }

        return [
            'result' => 1,
            'connection_id' => (int) $connectionId,
            'identity' => $identity,
            'lifetime_handle' => $pipes[0],
            'registration_handle' => $pipes[4],
            'keeper_process' => $process,
            'keeper_stdout' => $pipes[1],
        ];
    }

    /** @param array<string,mixed> $lease */
    public static function releaseKeeper(array $lease): void
    {
        $registrationHandle = $lease['registration_handle'] ?? null;
        if (is_resource($registrationHandle)) {
            fclose($registrationHandle);
        }
        $lifetimeHandle = $lease['lifetime_handle'] ?? null;
        if (is_resource($lifetimeHandle)) {
            fclose($lifetimeHandle);
        }

        $process = $lease['keeper_process'] ?? null;
        $stdout = $lease['keeper_stdout'] ?? null;
        if (! is_resource($process)) {
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_RELEASE_FAILED', 'Database lock keeper process handle is unavailable.');
        }

        $deadline = microtime(true) + 8.0;
        $status = null;
        do {
            $status = proc_get_status($process);
            if (! ($status['running'] ?? false)) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        if (is_array($status) && ($status['running'] ?? false)) {
            throw new VgRuntimeMutationException(
                70,
                'DATABASE_LOCK_KEEPER_RELEASE_TIMEOUT',
                'Database lock keeper continues to hold the host and database locks while a child process remains live.',
                true
            );
        }
        $releaseResponse = is_resource($stdout) ? stream_get_contents($stdout, 65536) : false;
        if (is_resource($stdout)) {
            fclose($stdout);
        }
        $exitCode = proc_close($process);
        $releaseAcknowledged = false;
        if (is_string($releaseResponse) && strlen($releaseResponse) < 65536) {
            try {
                $response = json_decode(trim($releaseResponse), true, 16, JSON_THROW_ON_ERROR);
                $releaseAcknowledged = is_array($response) && ($response['released'] ?? false) === true;
            } catch (JsonException) {
                $releaseAcknowledged = false;
            }
        }
        $observedExitCode = is_array($status) && is_int($status['exitcode'] ?? null)
            ? $status['exitcode']
            : -1;
        if (! $releaseAcknowledged
            || ($observedExitCode >= 0 && $observedExitCode !== 0)
            || ($exitCode >= 0 && $exitCode !== 0)) {
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_RELEASE_FAILED', 'Database lock keeper did not release cleanly.');
        }
    }

    /**
     * Read the current advisory-lock owner without acquiring or releasing it.
     *
     * @param array<string,mixed> $target
     */
    public static function statusLockOwner(
        array $target,
        string $wpRoot,
        string $lockName,
        ?string $socketOverride = null
    ): array {
        if (! VgRuntimeMutationCore::isAbsolutePathForDatabase($wpRoot) || ! is_dir($wpRoot)) {
            return ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'INVALID_WORDPRESS_ROOT'];
        }
        if ($lockName !== VgRuntimeMutationCore::advisoryLockName($target)) {
            return ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'INVALID_DATABASE_LOCK_NAME'];
        }
        $database = null;
        try {
            $configuration = self::readWordPressConfiguration($wpRoot);
            $transport = VgRuntimeMutationCore::parseDbHost($configuration['DB_HOST'], $socketOverride);
            $database = self::connectDatabase($configuration, $transport);
            if (self::fetchSingleValue($database, 'SELECT DATABASE()') !== $configuration['DB_NAME']) {
                throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH');
            }
            $identity = self::readTargetIdentity($database, $configuration, $transport);
            VgRuntimeMutationCore::validateTargetIdentity($target, $identity, $wpRoot);
            $owner = self::fetchPreparedValue($database, 'SELECT IS_USED_LOCK(?)', $lockName);
            if ($owner === null || $owner === false || $owner === '') {
                return ['state' => 'free', 'owner_connection_id' => null, 'error_code' => null];
            }
            if ((! is_int($owner) && ! is_string($owner)) || ! ctype_digit((string) $owner) || (int) $owner < 1) {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_OWNER_QUERY_FAILED');
            }
            return ['state' => 'held', 'owner_connection_id' => (int) $owner, 'error_code' => null];
        } catch (VgRuntimeMutationException $exception) {
            return ['state' => 'error', 'owner_connection_id' => null, 'error_code' => $exception->errorCode];
        } catch (Throwable) {
            return ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'DATABASE_STATUS_FAILED'];
        } finally {
            if ($database instanceof mysqli) {
                $database->close();
            }
        }
    }

    /** @param list<string> $arguments */
    public static function keeperMain(array $arguments): int
    {
        $options = [];
        foreach ($arguments as $argument) {
            if (! is_string($argument) || ! preg_match('/\A--([a-z-]+)=(.*)\z/s', $argument, $matches)) {
                self::writeKeeperResponse(['ok' => false, 'exit_code' => 71, 'error_code' => 'INVALID_KEEPER_ARGUMENTS']);
                return 71;
            }
            if (array_key_exists($matches[1], $options)
                || ! in_array($matches[1], ['target', 'wp-root', 'lock-name', 'db-socket'], true)) {
                self::writeKeeperResponse(['ok' => false, 'exit_code' => 71, 'error_code' => 'INVALID_KEEPER_ARGUMENTS']);
                return 71;
            }
            $options[$matches[1]] = $matches[2];
        }
        if (! isset($options['target'], $options['wp-root'], $options['lock-name'])
            || ! array_key_exists('db-socket', $options)
            || count($options) !== 4) {
            self::writeKeeperResponse(['ok' => false, 'exit_code' => 71, 'error_code' => 'INVALID_KEEPER_ARGUMENTS']);
            return 71;
        }

        $targetId = $options['target'];
        $wpRoot = $options['wp-root'];
        $lockName = $options['lock-name'];
        $socketOverride = ($options['db-socket'] ?? '') !== '' ? $options['db-socket'] : null;
        $database = null;
        $lockAcquired = false;
        $responseSent = false;
        try {
            $registry = VgRuntimeMutationCore::loadTargetRegistry(__DIR__ . '/runtime-mutation-targets.json');
            $target = VgRuntimeMutationCore::resolveTarget($registry, $targetId);
            if ($lockName !== VgRuntimeMutationCore::advisoryLockName($target)) {
                throw new VgRuntimeMutationException(71, 'INVALID_DATABASE_LOCK_NAME');
            }
            if (! VgRuntimeMutationCore::isAbsolutePathForDatabase($wpRoot) || ! is_dir($wpRoot)) {
                throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH');
            }

            $configuration = self::readWordPressConfiguration($wpRoot);
            $transport = VgRuntimeMutationCore::parseDbHost($configuration['DB_HOST'], $socketOverride);
            if (! class_exists(mysqli::class)) {
                throw new VgRuntimeMutationException(70, 'MYSQLI_UNAVAILABLE');
            }
            $database = self::connectDatabase($configuration, $transport);

            $actualDatabaseName = self::fetchSingleValue($database, 'SELECT DATABASE()');
            if ($actualDatabaseName !== $configuration['DB_NAME']) {
                throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH');
            }
            $lockResult = self::fetchPreparedValue($database, 'SELECT GET_LOCK(?, 0)', $lockName);
            VgRuntimeMutationCore::interpretGetLockResult($lockResult);
            $lockAcquired = true;
            $connectionId = self::fetchSingleValue($database, 'SELECT CONNECTION_ID()');
            if (! ctype_digit((string) $connectionId) || (int) $connectionId < 1) {
                throw new VgRuntimeMutationException(70, 'DATABASE_CONNECTION_ID_UNAVAILABLE');
            }

            $identity = self::readTargetIdentity($database, $configuration, $transport);
            VgRuntimeMutationCore::validateTargetIdentity($target, $identity, $wpRoot);

            self::writeKeeperResponse([
                'ok' => true,
                'connection_id' => (int) $connectionId,
                'identity' => $identity,
            ]);
            $responseSent = true;
            $supervisorPid = self::readProcessGroupRegistration();
            if ($supervisorPid === null) {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED');
            }
            // Only an explicit acknowledgement from the registered supervisor permits release.
            self::waitForLifetimePipe($supervisorPid);

            $releaseResult = self::fetchPreparedValue($database, 'SELECT RELEASE_LOCK(?)', $lockName);
            if (! in_array($releaseResult, [1, '1'], true)) {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_RELEASE_FAILED');
            }
            $lockAcquired = false;
            self::writeKeeperResponse(['released' => true]);
            return 0;
        } catch (VgRuntimeMutationException $exception) {
            if (! $responseSent) {
                self::writeKeeperResponse([
                    'ok' => false,
                    'exit_code' => $exception->exitCode,
                    'error_code' => $exception->errorCode,
                ]);
            }
            return $exception->exitCode;
        } catch (Throwable) {
            if (! $responseSent) {
                self::writeKeeperResponse(['ok' => false, 'exit_code' => 70, 'error_code' => 'DATABASE_LOCK_KEEPER_FAILED']);
            }
            return 70;
        } finally {
            if ($lockAcquired && $database instanceof mysqli) {
                try {
                    self::fetchPreparedValue($database, 'SELECT RELEASE_LOCK(?)', $lockName);
                } catch (Throwable) {
                    // Closing the dedicated connection below is the final lock release.
                }
            }
            if ($database instanceof mysqli) {
                $database->close();
            }
        }
    }

    /** @return array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string,table_prefix:string} */
    private static function readWordPressConfiguration(string $wpRoot): array
    {
        return [
            'DB_NAME' => self::readWordPressConfigValue($wpRoot, 'DB_NAME', 'constant'),
            'DB_USER' => self::readWordPressConfigValue($wpRoot, 'DB_USER', 'constant'),
            'DB_PASSWORD' => self::readWordPressConfigValue($wpRoot, 'DB_PASSWORD', 'constant'),
            'DB_HOST' => self::readWordPressConfigValue($wpRoot, 'DB_HOST', 'constant'),
            'table_prefix' => self::readWordPressConfigValue($wpRoot, 'table_prefix', 'variable'),
        ];
    }

    private static function readWordPressConfigValue(string $wpRoot, string $name, string $type): string
    {
        $command = VgRuntimeMutationCore::descriptorSafeCommand([
            'wp',
            '--path=' . $wpRoot,
            '--quiet',
            'config',
            'get',
            $name,
            '--type=' . $type,
            '--format=json',
        ], []);
        $process = @proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ], $pipes, null, VgRuntimeMutationCore::safeEnvironment(self::readEnvironment()));
        if (! is_resource($process)) {
            throw new VgRuntimeMutationException(71, 'WP_CONFIG_READ_FAILED');
        }
        stream_set_timeout($pipes[1], 15);
        $output = stream_get_contents($pipes[1], 65536);
        $timedOut = (bool) (stream_get_meta_data($pipes[1])['timed_out'] ?? false);
        if ($timedOut || ! is_string($output) || strlen($output) >= 65536) {
            @proc_terminate($process, SIGKILL);
        }
        fclose($pipes[1]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $timedOut || ! is_string($output) || strlen($output) >= 65536) {
            throw new VgRuntimeMutationException(71, 'WP_CONFIG_READ_FAILED');
        }
        try {
            $value = json_decode(trim($output), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new VgRuntimeMutationException(71, 'WP_CONFIG_VALUE_INVALID');
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new VgRuntimeMutationException(71, 'WP_CONFIG_VALUE_INVALID');
        }
        return (string) $value;
    }

    /** @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string,table_prefix:string} $configuration @param array{host:string,port:?int,socket:?string} $transport */
    private static function connectDatabase(array $configuration, array $transport): mysqli
    {
        if (! class_exists(mysqli::class)) {
            throw new VgRuntimeMutationException(70, 'MYSQLI_UNAVAILABLE');
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $database = mysqli_init();
        if (! $database instanceof mysqli) {
            throw new VgRuntimeMutationException(70, 'DATABASE_CONNECT_FAILED');
        }
        $database->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $database->options(MYSQLI_OPT_READ_TIMEOUT, 5);
        }
        if (defined('MYSQLI_OPT_WRITE_TIMEOUT')) {
            $database->options(MYSQLI_OPT_WRITE_TIMEOUT, 5);
        }
        $connected = @$database->real_connect(
            $transport['host'],
            $configuration['DB_USER'],
            $configuration['DB_PASSWORD'],
            $configuration['DB_NAME'],
            $transport['port'],
            $transport['socket']
        );
        if (! $connected) {
            $database->close();
            throw new VgRuntimeMutationException(70, 'DATABASE_CONNECT_FAILED');
        }
        return $database;
    }

    /** @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string,table_prefix:string} $configuration @param array{host:string,port:?int,socket:?string} $transport @return array{database_name:string,database_host:string,home:string,site:string} */
    private static function readTargetIdentity(
        mysqli $database,
        array $configuration,
        array $transport
    ): array {
        $prefix = $configuration['table_prefix'];
        if (! preg_match('/\A[A-Za-z0-9_]{1,58}\z/', $prefix)) {
            throw new VgRuntimeMutationException(71, 'INVALID_TABLE_PREFIX');
        }
        $optionsTable = '`' . $prefix . 'options`';
        $optionsResult = @$database->query(
            "SELECT option_name, option_value FROM {$optionsTable} WHERE option_name IN ('home', 'siteurl')"
        );
        if (! $optionsResult instanceof mysqli_result) {
            throw new VgRuntimeMutationException(70, 'TARGET_IDENTITY_QUERY_FAILED');
        }
        $siteOptions = [];
        while ($row = $optionsResult->fetch_assoc()) {
            if (isset($row['option_name'], $row['option_value'])
                && in_array($row['option_name'], ['home', 'siteurl'], true)) {
                $siteOptions[$row['option_name']] = (string) $row['option_value'];
            }
        }
        $optionsResult->free();
        if (! isset($siteOptions['home'], $siteOptions['siteurl'])) {
            throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH');
        }
        return [
            'database_name' => self::fetchSingleValue($database, 'SELECT DATABASE()'),
            'database_host' => $transport['host'],
            'home' => $siteOptions['home'],
            'site' => $siteOptions['siteurl'],
        ];
    }

    private static function fetchSingleValue(mysqli $database, string $query): string
    {
        $result = @$database->query($query);
        if (! $result instanceof mysqli_result) {
            throw new VgRuntimeMutationException(70, 'DATABASE_QUERY_FAILED');
        }
        $row = $result->fetch_row();
        $result->free();
        if (! is_array($row) || ! isset($row[0])) {
            throw new VgRuntimeMutationException(70, 'DATABASE_QUERY_FAILED');
        }
        return (string) $row[0];
    }

    private static function fetchPreparedValue(mysqli $database, string $query, string $argument): mixed
    {
        $statement = @$database->prepare($query);
        if (! $statement instanceof mysqli_stmt || ! $statement->bind_param('s', $argument) || ! $statement->execute()) {
            throw new VgRuntimeMutationException(70, 'DATABASE_QUERY_FAILED');
        }
        $value = null;
        if (! $statement->bind_result($value) || ! $statement->fetch()) {
            $statement->close();
            throw new VgRuntimeMutationException(70, 'DATABASE_QUERY_FAILED');
        }
        $statement->close();
        return $value;
    }

    private static function waitForLifetimePipe(int $supervisorPid): void
    {
        $expectedAcknowledgement = 'VGNOVA_RUNTIME_MUTATION_SUPERVISOR_COMPLETE_V1:' . $supervisorPid . "\n";
        $acknowledgement = fgets(STDIN, 128);
        if ($acknowledgement !== $expectedAcknowledgement) {
            self::retainLocksAfterUnexpectedSupervisorExit();
        }
        while (! feof(STDIN)) {
            $chunk = fread(STDIN, 8192);
            if ($chunk === false || $chunk !== '') {
                self::retainLocksAfterUnexpectedSupervisorExit();
            }
            if ($chunk === '' && ! feof(STDIN)) {
                usleep(10000);
            }
        }
    }

    private static function retainLocksAfterUnexpectedSupervisorExit(): void
    {
        while (true) {
            sleep(60);
        }
    }

    private static function readProcessGroupRegistration(): ?int
    {
        $registration = @fopen('php://fd/4', 'r');
        if (! is_resource($registration)) {
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED');
        }
        $line = fgets($registration, 32);
        $timedOut = (bool) (stream_get_meta_data($registration)['timed_out'] ?? false);
        $atEnd = feof($registration);
        fclose($registration);
        if ($timedOut) {
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED');
        }
        if (! is_string($line)) {
            if ($atEnd) {
                return null;
            }
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED');
        }
        if (preg_match('/\A([1-9][0-9]{0,9})\n\z/', $line, $matches) !== 1 || (int) $matches[1] < 2) {
            throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_PROTOCOL_FAILED');
        }
        return (int) $matches[1];
    }

    /** @param array<string,mixed> $response */
    private static function writeKeeperResponse(array $response): void
    {
        $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }

    /** @return array<string,string> */
    private static function readEnvironment(): array
    {
        $environment = getenv();
        return is_array($environment) ? $environment : [];
    }
}
