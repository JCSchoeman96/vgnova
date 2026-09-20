<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Services;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;
use Traversable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

/**
 * Reads authenticated-user course access from Masteriyo.
 */
final class LearningService implements DashboardServiceInterface
{
    private mixed $provider;

    public function __construct(mixed $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $user_id): array
    {
        if (! $this->is_authenticated_user($user_id)) {
            return DashboardCard::empty(
                'learning',
                AfrikaansLabels::LEARNING,
                'empty',
                'Meld aan om jou kursusse te sien.'
            );
        }

        try {
            $courses_result = $this->courses($user_id);
            if (! $courses_result['ok']) {
                return $this->unavailable();
            }

            $courses = $this->collection($courses_result['value']);
            if ($courses === null) {
                return $this->unavailable();
            }

            $items = [];
            foreach ($courses as $user_course) {
                $item_result = $this->item($user_course, $user_id);
                if ($item_result['error']) {
                    return $this->unavailable();
                }

                if ($item_result['item'] !== null) {
                    $items[] = $item_result['item'];
                }
            }

            if ($items === []) {
                return DashboardCard::empty(
                    'learning',
                    AfrikaansLabels::LEARNING,
                    'empty',
                    'Geen kursusse met toegang nie.'
                );
            }

            return DashboardCard::make(
                'learning',
                AfrikaansLabels::LEARNING,
                'active',
                sprintf('%d kursus%s', count($items), count($items) === 1 ? '' : 'se'),
                [],
                $items
            );
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function courses(int $user_id): array
    {
        if ($this->provider !== null) {
            if (! is_object($this->provider)) {
                return ['ok' => false, 'value' => null];
            }

            $result = $this->provider_call(
                ['user_courses', 'get_user_courses', 'courses'],
                [
                    [$user_id, ['status' => 'any', 'per_page' => -1]],
                    [$user_id, 'any'],
                    [$user_id],
                    [['user_id' => $user_id, 'status' => 'any', 'per_page' => -1]],
                ]
            );

            return $result['found'] && $result['ok']
                ? ['ok' => true, 'value' => $result['value']]
                : ['ok' => false, 'value' => null];
        }

        $class = '\\Masteriyo\\Query\\UserCourseQuery';
        if (! class_exists($class)) {
            return ['ok' => false, 'value' => null];
        }

        $query_args = ['user_id' => $user_id, 'status' => 'any', 'per_page' => -1];
        try {
            $query = new $class($query_args);
        } catch (Throwable) {
            $query = null;
        }

        if (is_object($query) && method_exists($query, 'get_user_courses')) {
            $result = $this->invoke_method($query, 'get_user_courses', [[], [$query_args], [$user_id, $query_args]]);
            if ($result['ok']) {
                return ['ok' => true, 'value' => $result['value']];
            }

            return ['ok' => false, 'value' => null];
        }

        if (is_callable([$class, 'get_user_courses'])) {
            try {
                try {
                    return ['ok' => true, 'value' => $class::get_user_courses($query_args)];
                } catch (\ArgumentCountError) {
                    return ['ok' => true, 'value' => $class::get_user_courses()];
                }
            } catch (Throwable) {
                return ['ok' => false, 'value' => null];
            }
        }

        return ['ok' => false, 'value' => null];
    }

    /**
     * @return array{error: bool, item: array<string, mixed>|null}
     */
    private function item(mixed $user_course, int $user_id): array
    {
        $record_user_id = $this->record_value($user_course, ['get_user_id'], ['user_id']);
        $record_user = $this->canonical_id($record_user_id);
        if ($record_user === null) {
            return ['error' => true, 'item' => null];
        }

        // A query must still be checked after it returns. Do not trust a broad
        // provider query to enforce the current user's ownership boundary.
        if ($record_user !== $user_id) {
            return ['error' => false, 'item' => null];
        }

        $course_id = $this->canonical_id(
            $this->record_value($user_course, ['get_course_id'], ['course_id', 'id'])
        );
        if ($course_id === null) {
            return ['error' => true, 'item' => null];
        }

        $access = $this->access_check($course_id, $user_id);
        if (! $access['ok']) {
            return ['error' => true, 'item' => null];
        }

        if (! $access['allowed']) {
            return ['error' => false, 'item' => null];
        }

        $course_result = $this->course($user_course, $course_id);
        if (! $course_result['ok']) {
            return ['error' => true, 'item' => null];
        }

        $course = $course_result['value'];
        $title = $this->scalar_string(
            $this->record_value($course, ['get_name', 'get_title', 'get_course_title'], ['name', 'title', 'post_title'])
        );
        if ($title === '') {
            return ['error' => true, 'item' => null];
        }

        $url = $this->safe_url(
            $this->scalar_string(
                $this->record_value($user_course, ['get_permalink', 'get_course_permalink'], ['permalink', 'url'])
            )
        );
        if ($url === '') {
            $url = $this->safe_url(
                $this->scalar_string(
                    $this->record_value($course, ['get_permalink', 'get_course_permalink'], ['permalink', 'url'])
                )
            );
        }

        $status = $this->status(
            $this->scalar_string($this->record_value($user_course, ['get_status'], ['status', 'enrollment_status']))
        );

        $progress = $this->progress($course_id, $user_id);
        if (! $progress['ok']) {
            return ['error' => true, 'item' => null];
        }

        $progress_info = $this->progress_info($progress['value']);
        $percentage = $progress_info['percentage'];
        $next_lesson = $progress_info['next_lesson'];
        $next_lesson_url = $this->safe_url($progress_info['next_lesson_url']);
        if (
            $percentage !== ''
            && (int) $percentage < 100
            && is_object($course)
            && method_exists($course, 'continue_course_url')
        ) {
            try {
                $continue_url = $course->continue_course_url($progress['value']);
            } catch (Throwable) {
                $continue_url = '';
            }

            $continue_url = $this->safe_url($this->scalar_string($continue_url));
            if ($continue_url !== '') {
                $next_lesson_url = $continue_url;
            }
        }
        $certificate_url = '';
        if ($percentage !== '' && (int) $percentage >= 100) {
            $certificate_url = $this->certificate_url($course_id, $user_id);
        }

        $actions = [];
        $course_action = $this->action('open', 'Maak kursus oop', $url);
        if ($course_action !== null) {
            $actions['open'] = $course_action;
        }

        $next_action = $this->action('next-lesson', 'Gaan voort met les', $next_lesson_url);
        if ($next_action !== null) {
            $actions['next-lesson'] = $next_action;
        }

        $certificate_action = $this->action('certificate', 'Laai sertifikaat af', $certificate_url);
        if ($certificate_action !== null) {
            $actions['certificate'] = $certificate_action;
        }

        return [
            'error' => false,
            'item'  => [
                'course_id'            => (string) $course_id,
                'title'                => $title,
                'course_title'        => $title,
                'course_url'           => $url,
                'url'                  => $url,
                'enrollment_state'     => $status,
                'enrollment_label'     => $this->status_label($status),
                'enrolment_state'     => $status,
                'enrolment_label'     => $this->status_label($status),
                'status'               => $status,
                'status_label'         => $this->status_label($status),
                'completion_percentage'=> $percentage,
                'progress'             => $percentage,
                'completion_label'     => $percentage === '' ? '' : ((string) $percentage . '% voltooi'),
                'next_lesson'          => $next_lesson,
                'next_lesson_url'      => $next_lesson_url,
                'certificate_url'      => $certificate_url,
                'certificate_link'     => $certificate_url,
                'actions'              => $actions,
            ],
        ];
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function course(mixed $user_course, int $course_id): array
    {
        $course = $this->record_value($user_course, ['get_course'], ['course']);
        if ($course !== null) {
            return ['ok' => true, 'value' => $course];
        }

        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_call(['course', 'get_course'], [[$course_id]]);
            if ($result['found']) {
                return $result['ok'] && $result['value'] !== null
                    ? ['ok' => true, 'value' => $result['value']]
                    : ['ok' => false, 'value' => null];
            }
        }

        if (function_exists('masteriyo_get_course')) {
            try {
                $course = \masteriyo_get_course($course_id);
            } catch (Throwable) {
                return ['ok' => false, 'value' => null];
            }

            return $course !== null ? ['ok' => true, 'value' => $course] : ['ok' => false, 'value' => null];
        }

        return ['ok' => false, 'value' => null];
    }

    /**
     * @return array{ok: bool, allowed: bool}
     */
    private function access_check(int $course_id, int $user_id): array
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_user_course_call(
                ['is_enrolled', 'is_user_enrolled', 'user_can_access', 'has_access'],
                $course_id,
                $user_id
            );
            if ($result['found']) {
                return $result['ok']
                    ? ['ok' => true, 'allowed' => $result['value'] === true]
                    : ['ok' => false, 'allowed' => false];
            }
        }

        if (function_exists('masteriyo_is_user_enrolled_in_course')) {
            try {
                return [
                    'ok'      => true,
                    'allowed' => \masteriyo_is_user_enrolled_in_course($course_id, $user_id) === true,
                ];
            } catch (Throwable) {
                return ['ok' => false, 'allowed' => false];
            }
        }

        return ['ok' => false, 'allowed' => false];
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function progress(int $course_id, int $user_id): array
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_user_course_call(
                ['course_progress', 'get_course_progress', 'progress'],
                $course_id,
                $user_id
            );
            if ($result['found']) {
                return $result['ok']
                    ? ['ok' => true, 'value' => $result['value']]
                    : ['ok' => false, 'value' => null];
            }

            return ['ok' => true, 'value' => null];
        }

        if (function_exists('masteriyo_get_course_progress_by_user_and_course')) {
            try {
                return [
                    'ok'    => true,
                    'value' => \masteriyo_get_course_progress_by_user_and_course($user_id, $course_id),
                ];
            } catch (Throwable) {
                return ['ok' => false, 'value' => null];
            }
        }

        $class = '\\Masteriyo\\Query\\CourseProgressQuery';
        if (! class_exists($class)) {
            return ['ok' => true, 'value' => null];
        }

        $query_args = ['course_id' => $course_id, 'user_id' => $user_id];
        try {
            $query = new $class($query_args);
        } catch (Throwable) {
            return ['ok' => false, 'value' => null];
        }

        if (! method_exists($query, 'get_course_progress')) {
            return ['ok' => false, 'value' => null];
        }

        // The query is already scoped by both IDs; the native API reads those
        // arguments from the constructor and exposes a zero-argument getter.
        $result = $this->invoke_method($query, 'get_course_progress', [[]]);

        return $result['ok'] ? ['ok' => true, 'value' => $result['value']] : ['ok' => false, 'value' => null];
    }

    /**
     * @return array{percentage: int|string, next_lesson: string, next_lesson_url: string}
     */
    private function progress_info(mixed $progress): array
    {
        if ($progress instanceof Traversable) {
            $progress = iterator_to_array($progress, false);
        }

        if (is_array($progress) && $this->is_list($progress)) {
            $progress = $progress[0] ?? null;
        }

        if (is_scalar($progress) && $progress !== '' && ! is_numeric($progress)) {
            throw new \RuntimeException('Course progress shape is invalid.');
        }

        $summary = $progress;
        if (is_object($progress) && method_exists($progress, 'get_summary')) {
            try {
                $summary = $progress->get_summary('all');
            } catch (Throwable) {
                throw new \RuntimeException('Course progress summary could not be read.');
            }
        }

        if (is_array($summary) && $summary !== [] && ! $this->has_progress_fields($summary)) {
            throw new \RuntimeException('Course progress summary shape is invalid.');
        }

        if (is_object($summary) && ! method_exists($summary, 'get_summary') && ! $this->has_progress_fields($summary)) {
            throw new \RuntimeException('Course progress summary shape is invalid.');
        }

        $percentage = $this->percentage($summary);
        $lesson = $this->value_from_source($summary, ['next_lesson', 'next_item']);
        if ($lesson === null && $progress !== $summary) {
            $lesson = $this->value_from_source($progress, ['next_lesson', 'next_item']);
        }

        $next_lesson = '';
        $next_lesson_url = '';
        if (is_array($lesson) || is_object($lesson)) {
            $next_lesson = $this->scalar_string(
                $this->record_value($lesson, ['get_name', 'get_title'], ['title', 'name', 'post_title'])
            );
            $next_lesson_url = $this->scalar_string(
                $this->record_value($lesson, ['get_permalink', 'get_url'], ['url', 'permalink'])
            );
            if ($next_lesson === '') {
                $next_lesson = $this->scalar_string($lesson);
            }
        } else {
            $next_lesson = $this->scalar_string($lesson);
        }

        if ($next_lesson === '') {
            $next_lesson = $this->scalar_string($this->value_from_source($summary, ['next_lesson_title']));
        }
        $next_lesson_url = $this->scalar_string(
            $this->value_from_source($summary, ['next_lesson_url', 'next_item_url'])
        ) ?: $next_lesson_url;

        return [
            'percentage'       => $percentage,
            'next_lesson'      => $next_lesson,
            'next_lesson_url'  => $next_lesson_url,
        ];
    }

    private function percentage(mixed $summary): int|string
    {
        if ($summary === null || $summary === false || $summary === '') {
            return '';
        }

        foreach (['percentage', 'completion_percentage', 'completed_percentage', 'progress_percentage', 'percent'] as $key) {
            $value = $this->value_from_source($summary, [$key]);
            if (is_numeric($value)) {
                return $this->normalise_percentage((float) $value, false);
            }
        }

        $progress = $this->value_from_source($summary, ['progress']);
        if (is_numeric($progress)) {
            return $this->normalise_percentage((float) $progress, true);
        }

        $completed = $this->value_from_source($summary, ['completed', 'completed_count', 'completed_items', 'completed_items_count', 'completed_lessons']);
        $total = $this->value_from_source($summary, ['total', 'total_count', 'total_items', 'total_items_count', 'total_lessons']);
        if (! is_numeric($completed) || ! is_numeric($total)) {
            $total_summary = $this->value_from_source($summary, ['total']);
            if (! is_numeric($completed)) {
                $completed = $this->value_from_source(
                    $total_summary,
                    ['completed', 'completed_count', 'completed_items', 'completed_items_count', 'completed_lessons']
                );
            }
            if (! is_numeric($total)) {
                $total = $this->value_from_source(
                    $total_summary,
                    ['total', 'total_count', 'total_items', 'total_items_count', 'total_lessons']
                );
            }
        }
        if (is_numeric($completed) && is_numeric($total) && (float) $total > 0) {
            return $this->normalise_percentage(((float) $completed / (float) $total) * 100);
        }

        return '';
    }

    private function normalise_percentage(float $percentage, bool $fractional = false): int
    {
        if ($fractional && $percentage >= 0 && $percentage <= 1) {
            $percentage *= 100;
        }

        return (int) max(0, min(100, round($percentage)));
    }

    private function has_progress_fields(mixed $summary): bool
    {
        foreach ([
            'percentage',
            'completion_percentage',
            'completed_percentage',
            'progress_percentage',
            'percent',
            'progress',
            'completed',
            'completed_count',
            'completed_items',
            'completed_items_count',
            'completed_lessons',
            'total',
            'total_count',
            'total_items',
            'total_items_count',
            'total_lessons',
            'next_lesson',
            'next_item',
            'next_lesson_title',
            'next_lesson_url',
            'next_item_url',
        ] as $key) {
            if (is_array($summary) && array_key_exists($key, $summary)) {
                return true;
            }

            if (is_object($summary) && (property_exists($summary, $key) || isset($summary->{$key}))) {
                return true;
            }
        }

        return false;
    }

    private function certificate_url(int $course_id, int $user_id): string
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $direct = $this->provider_call(
                ['certificate_url', 'certificate_download_url'],
                [[$course_id, $user_id], [$course_id]]
            );
            if ($direct['found'] && $direct['ok']) {
                return $this->safe_url($this->scalar_string($direct['value']));
            }

            $certificate = $this->provider_call(['certificate_id', 'certificate'], [[$course_id, $user_id], [$course_id]]);
            if ($certificate['found'] && $certificate['ok']) {
                $download = $this->provider_call(
                    ['certificate_download_url', 'download_certificate'],
                    [[$certificate['value'], $course_id, $user_id], [$certificate['value']]]
                );
                if ($download['found'] && $download['ok']) {
                    return $this->safe_url($this->scalar_string($download['value']));
                }
            }

            return '';
        }

        if (! function_exists('masteriyo_get_course_certificate_id')) {
            return '';
        }

        try {
            $certificate_id = $this->invoke_function(
                'masteriyo_get_course_certificate_id',
                [[$course_id, $user_id], [$course_id]]
            );
        } catch (Throwable) {
            return '';
        }

        if ($certificate_id === null || $certificate_id === false || $certificate_id === '' || (is_numeric($certificate_id) && (int) $certificate_id <= 0)) {
            return '';
        }

        if (! function_exists('masteriyo_generate_certificate_download_url')) {
            return '';
        }

        try {
            $url = $this->invoke_function(
                'masteriyo_generate_certificate_download_url',
                [[$course_id], [$course_id, $user_id]]
            );
        } catch (Throwable) {
            return '';
        }

        return $this->safe_url($this->scalar_string($url));
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, array<int, mixed>> $variants
     * @return array{found: bool, ok: bool, value: mixed}
     */
    private function provider_call(array $methods, array $variants): array
    {
        foreach ($methods as $method) {
            if (! is_object($this->provider) || ! method_exists($this->provider, $method)) {
                continue;
            }

            return $this->invoke_method($this->provider, $method, $variants);
        }

        return ['found' => false, 'ok' => false, 'value' => null];
    }

    /**
     * @param array<int, string> $methods
     * @return array{found: bool, ok: bool, value: mixed}
     */
    private function provider_user_course_call(array $methods, int $course_id, int $user_id): array
    {
        foreach ($methods as $method) {
            if (! is_object($this->provider) || ! method_exists($this->provider, $method)) {
                continue;
            }

            try {
                $reflection = new ReflectionMethod($this->provider, $method);
            } catch (Throwable) {
                return ['found' => true, 'ok' => false, 'value' => null];
            }

            return $this->invoke_method(
                $this->provider,
                $method,
                $this->user_course_variants($reflection, $course_id, $user_id)
            );
        }

        return ['found' => false, 'ok' => false, 'value' => null];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function user_course_variants(ReflectionMethod $reflection, int $course_id, int $user_id): array
    {
        $parameters = $reflection->getParameters();
        $first_role = isset($parameters[0]) ? $this->parameter_role($parameters[0]) : '';
        $second_role = isset($parameters[1]) ? $this->parameter_role($parameters[1]) : '';

        $course_first = [$course_id, $user_id];
        $user_first = [$user_id, $course_id];
        if ($first_role === 'user' && $second_role === 'course') {
            return [$user_first, $course_first, [['course_id' => $course_id, 'user_id' => $user_id]]];
        }

        return [$course_first, $user_first, [['course_id' => $course_id, 'user_id' => $user_id]]];
    }

    private function parameter_role(\ReflectionParameter $parameter): string
    {
        $name = strtolower(str_replace(['_', '-'], '', $parameter->getName()));
        if (str_contains($name, 'user')) {
            return 'user';
        }

        if (str_contains($name, 'course')) {
            return 'course';
        }

        return '';
    }

    /**
     * @param array<int, array<int, mixed>> $variants
     * @return array{found: bool, ok: bool, value: mixed}
     */
    private function invoke_method(object $object, string $method, array $variants): array
    {
        try {
            $reflection = new ReflectionMethod($object, $method);
        } catch (Throwable) {
            return ['found' => true, 'ok' => false, 'value' => null];
        }

        foreach ($variants as $arguments) {
            if (! $this->accepts_arguments($reflection, count($arguments)) || ! $this->arguments_match($reflection, $arguments)) {
                continue;
            }

            try {
                if ($reflection->isStatic()) {
                    $class = $reflection->getDeclaringClass()->getName();

                    return ['found' => true, 'ok' => true, 'value' => $class::{$method}(...$arguments)];
                }

                return ['found' => true, 'ok' => true, 'value' => $object->{$method}(...$arguments)];
            } catch (Throwable) {
                return ['found' => true, 'ok' => false, 'value' => null];
            }
        }

        return ['found' => true, 'ok' => false, 'value' => null];
    }

    /**
     * @param array<int, array<int, mixed>> $variants
     */
    private function invoke_function(string $function, array $variants): mixed
    {
        $reflection = new \ReflectionFunction($function);
        foreach ($variants as $arguments) {
            if (! $this->accepts_arguments($reflection, count($arguments)) || ! $this->arguments_match($reflection, $arguments)) {
                continue;
            }

            return $function(...$arguments);
        }

        return null;
    }

    private function accepts_arguments(\ReflectionFunctionAbstract $reflection, int $count): bool
    {
        $required = $reflection->getNumberOfRequiredParameters();
        $maximum = $reflection->isVariadic() ? PHP_INT_MAX : $reflection->getNumberOfParameters();

        return $count >= $required && $count <= $maximum;
    }

    /**
     * Avoid calling a typed provider method with the wrong fixture variant.
     * This also lets tests inject either an integer user ID or a user object.
     *
     * @param array<int, mixed> $arguments
     */
    private function arguments_match(\ReflectionFunctionAbstract $reflection, array $arguments): bool
    {
        foreach ($reflection->getParameters() as $index => $parameter) {
            if (! array_key_exists($index, $arguments)) {
                continue;
            }

            $type = $parameter->getType();
            if ($type === null || $type->allowsNull() && $arguments[$index] === null) {
                continue;
            }

            $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
            $matches = false;
            foreach ($types as $candidate) {
                if (! $candidate instanceof ReflectionNamedType) {
                    continue;
                }

                $name = $candidate->getName();
                $value = $arguments[$index];
                $matches = match ($name) {
                    'mixed'    => true,
                    'array'    => is_array($value),
                    'bool'     => is_bool($value),
                    'float'    => is_float($value),
                    'int'      => is_int($value),
                    'string'   => is_string($value),
                    'object'   => is_object($value),
                    'callable' => is_callable($value),
                    'iterable' => is_iterable($value),
                    default    => is_object($value) && $value instanceof $name,
                };
                if ($matches) {
                    break;
                }
            }

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function collection(mixed $value): ?array
    {
        if ($value instanceof Traversable) {
            try {
                $value = iterator_to_array($value, false);
            } catch (Throwable) {
                return null;
            }
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (is_object($value)) {
            foreach (['get_items', 'get_results', 'get_posts'] as $method) {
                if (! method_exists($value, $method)) {
                    continue;
                }

                try {
                    $result = $value->{$method}();
                } catch (Throwable) {
                    return null;
                }

                return $this->collection($result);
            }
        }

        return null;
    }

    private function record_value(mixed $source, array $methods, array $keys): mixed
    {
        if (is_object($source)) {
            foreach ($methods as $method) {
                if (! method_exists($source, $method)) {
                    continue;
                }

                try {
                    return $source->{$method}();
                } catch (Throwable) {
                    throw new \RuntimeException('Masteriyo model property could not be read.');
                }
            }
        }

        foreach ($keys as $key) {
            if (is_array($source) && array_key_exists($key, $source)) {
                return $source[$key];
            }

            if (is_object($source) && (property_exists($source, $key) || isset($source->{$key}))) {
                try {
                    return $source->{$key};
                } catch (Throwable) {
                    throw new \RuntimeException('Masteriyo model property could not be read.');
                }
            }
        }

        return null;
    }

    private function value_from_source(mixed $source, array $keys): mixed
    {
        return $this->record_value($source, [], $keys);
    }

    private function canonical_id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 && (string) $id === $value ? $id : null;
    }

    private function scalar_string(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function safe_url(string $url): string
    {
        if ($url === '') {
            return '';
        }

        try {
            return DashboardAction::normalize(DashboardAction::link('url', 'Skakel', $url))['url'];
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return array{key: string, label: string, url: string}|null
     */
    private function action(string $key, string $label, string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        try {
            return DashboardAction::link($key, $label, $url);
        } catch (Throwable) {
            return null;
        }
    }

    private function status(string $status): string
    {
        $status = strtolower(trim($status));

        return $status !== '' ? $status : 'enrolled';
    }

    private function status_label(string $status): string
    {
        return [
            'enrolled'  => 'Ingeskryf',
            'active'    => 'Aktief',
            'completed' => 'Voltooi',
            'pending'   => 'Hangende',
            'cancelled' => 'Gekanselleer',
            'expired'   => 'Verstreke',
        ][$status] ?? 'Status onbekend';
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(): array
    {
        return DashboardCard::empty(
            'learning',
            AfrikaansLabels::LEARNING,
            'unavailable',
            'Kursusse is tans nie beskikbaar nie.'
        );
    }

    private function is_authenticated_user(int $user_id): bool
    {
        if ($user_id <= 0 || ! function_exists('get_current_user_id')) {
            return false;
        }

        try {
            return (int) get_current_user_id() === $user_id;
        } catch (Throwable) {
            return false;
        }
    }

    private function is_list(array $value): bool
    {
        $index = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $index++) {
                return false;
            }
        }

        return true;
    }
}
