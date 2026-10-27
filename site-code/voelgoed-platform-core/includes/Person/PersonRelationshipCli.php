<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Person;

defined('ABSPATH') || exit;

/** Guarded maintenance commands for the derived Person relationship index. */
final class PersonRelationshipCli
{
    private const COMMAND = 'vg-person-relationship-index';

    private PersonRelationshipSchema $schema;
    private PersonRelationshipIndexer $indexer;
    private PersonRelationshipReconciler $reconciler;
    private mixed $mutation_guard;

    /** @param null|callable():mixed $mutation_guard */
    public function __construct(
        ?PersonRelationshipSchema $schema = null,
        ?PersonRelationshipIndexer $indexer = null,
        ?PersonRelationshipReconciler $reconciler = null,
        ?callable $mutation_guard = null,
    ) {
        $this->schema = $schema ?? new PersonRelationshipSchema();
        $this->indexer = $indexer ?? new PersonRelationshipIndexer(
            new PersonRelationshipSourceLock(),
            new PersonRelationshipSourceReader(),
            new PersonRelationshipValidator(),
            new PersonRelationshipRepository($this->schema),
        );
        $this->reconciler = $reconciler ?? new PersonRelationshipReconciler(
            new PersonRelationshipSourceReader(),
            new PersonRelationshipRepository($this->schema),
            new PersonRelationshipValidator(),
        );
        $this->mutation_guard = $mutation_guard ?? static function (): void {
            vg_runtime_mutation_assert_session('vgnova-local-10039');
        };
    }

    public function register(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command(self::COMMAND . ' setup', [$this, 'setup_command']);
            \WP_CLI::add_command(self::COMMAND . ' rebuild', [$this, 'rebuild_command']);
            \WP_CLI::add_command(self::COMMAND . ' reconcile', [$this, 'reconcile_command']);
        }
    }

    /** Install the derived index schema. */
    public function setup_command(array $args, array $assoc_args): void
    {
        $this->assert_mutation_session();
        $result = $this->schema->install();
        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }
        \WP_CLI::success('Person relationship index schema is ready.');
    }

    /** Rebuild all supported sources or one explicitly selected source. */
    public function rebuild_command(array $args, array $assoc_args): void
    {
        $this->assert_mutation_session();
        if (! $this->schema->is_ready()) {
            \WP_CLI::error('Person relationship index schema is not ready. Run setup first.');
        }

        $source_ids = $this->source_ids($assoc_args['source'] ?? null);
        $rebuilt = 0;
        foreach ($source_ids as $source_id) {
            $result = $this->indexer->rebuild_source($source_id);
            if (is_wp_error($result)) {
                \WP_CLI::error(sprintf('Could not rebuild source %d: %s', $source_id, $result->get_error_message()));
            }
            $rebuilt++;
        }
        \WP_CLI::success(sprintf('Rebuilt Person relationship index for %d supported source(s).', $rebuilt));
    }

    /** Inspect source/index agreement and optionally repair the derived index. */
    public function reconcile_command(array $args, array $assoc_args): void
    {
        $format = (string) ($assoc_args['format'] ?? 'table');
        if (! in_array($format, ['table', 'json'], true)) {
            \WP_CLI::error('Format must be table or json.');
        }
        $repair = array_key_exists('repair', $assoc_args) && $assoc_args['repair'] !== false;
        if ($repair) {
            $this->assert_mutation_session();
            if (! $this->schema->is_ready()) {
                \WP_CLI::error('Person relationship index schema is not ready. Run setup first.');
            }
        }

        $sources = [];
        $repaired = [];
        foreach ($this->source_ids($assoc_args['source'] ?? null) as $source_id) {
            $issues = $this->reconciler->inspect_source($source_id);
            if (is_wp_error($issues)) {
                $sources[] = ['source_post_id' => $source_id, 'error' => $this->error_details($issues)];
                continue;
            }
            if ($repair && $issues !== []) {
                $result = $this->indexer->rebuild_source($source_id);
                if (is_wp_error($result)) {
                    $sources[] = ['source_post_id' => $source_id, 'issues' => $issues, 'repair_error' => $this->error_details($result)];
                    continue;
                }
                $repaired[] = $source_id;
                $issues = $this->reconciler->inspect_source($source_id);
                if (is_wp_error($issues)) {
                    $sources[] = ['source_post_id' => $source_id, 'error' => $this->error_details($issues), 'repaired' => true];
                    continue;
                }
            }
            $sources[] = ['source_post_id' => $source_id, 'issues' => $issues];
        }

        $report = [
            'summary' => [
                'sources' => count($sources),
                'with_issues' => count(array_filter($sources, static fn (array $source): bool => ! empty($source['issues']) || isset($source['error']) || isset($source['repair_error']))),
                'repaired' => count($repaired),
            ],
            'sources' => $sources,
        ];
        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }
        \WP_CLI::line('Source ID | Issues | Repair');
        foreach ($sources as $source) {
            $issue_count = count($source['issues'] ?? []);
            $status = isset($source['error']) && isset($source['repaired']) ? 'reindexed, follow-up inspect failed: ' . $this->format_error($source['error'])
                : (isset($source['error']) ? 'inspect failed: ' . $this->format_error($source['error'])
                : (isset($source['repair_error']) ? 'repair failed: ' . $this->format_error($source['repair_error'])
                : (in_array($source['source_post_id'], $repaired, true) ? ($issue_count > 0 ? 'reindexed, issues remain' : 'reindexed') : ($issue_count > 0 ? 'issues remain' : 'unchanged'))));
            \WP_CLI::line(sprintf('%d | %d | %s', $source['source_post_id'], $issue_count, $status));
        }
        \WP_CLI::line(sprintf('Sources: %d, with issues: %d, repaired: %d', $report['summary']['sources'], $report['summary']['with_issues'], $report['summary']['repaired']));
    }

    /** @return list<int> */
    private function source_ids(mixed $requested_source): array
    {
        if ($requested_source !== null && $requested_source !== '') {
            $source_id = filter_var($requested_source, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $source = $source_id === false ? null : get_post($source_id);
            if (! is_object($source) || ! PersonRelationshipContract::supported_source_type((string) ($source->post_type ?? ''))) {
                \WP_CLI::error('Source must be an existing Article or Masteriyo Course.');
            }
            return [(int) $source_id];
        }

        $source_ids = [];
        $post_statuses = function_exists('get_post_stati')
            ? array_values(get_post_stati([], 'names'))
            : ['publish', 'future', 'draft', 'pending', 'private', 'trash'];
        foreach ([PersonRelationshipContract::ARTICLE_POST_TYPE, PersonRelationshipContract::COURSE_POST_TYPE] as $post_type) {
            $ids = get_posts([
                'post_type' => $post_type,
                'post_status' => $post_statuses,
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);
            foreach ($ids as $source_id) {
                $source_ids[] = (int) $source_id;
            }
        }
        return $source_ids;
    }

    private function assert_mutation_session(): void
    {
        ($this->mutation_guard)();
    }

    /** @return array{code:string,message:string} */
    private function error_details(\WP_Error $error): array
    {
        return ['code' => (string) $error->get_error_code(), 'message' => (string) $error->get_error_message()];
    }

    /** @param array{code:string,message:string} $error */
    private function format_error(array $error): string
    {
        return sprintf('%s: %s', $error['code'], $error['message']);
    }
}
