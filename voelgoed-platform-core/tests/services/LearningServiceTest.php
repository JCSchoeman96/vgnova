<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\LearningService;

final class VgpcLearningCourseFixture
{
    public function __construct(
        private int $id,
        private string $title,
        private string $permalink,
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_name(): string
    {
        return $this->title;
    }

    public function get_permalink(): string
    {
        return $this->permalink;
    }
}

final class VgpcLearningContinuingCourseFixture
{
    public int $continue_calls = 0;

    public function __construct(
        private int $id,
        private string $title,
        private string $permalink,
        private string $continue_url,
    ) {
    }

    public function get_name(): string
    {
        return $this->title;
    }

    public function get_permalink(): string
    {
        return $this->permalink;
    }

    public function continue_course_url(mixed $course_progress): string
    {
        ++$this->continue_calls;

        return $this->continue_url;
    }
}

final class VgpcLearningUserCourseFixture
{
    public function __construct(
        private int $course_id,
        private int $user_id,
        private string $status,
        private object $course,
        private string $permalink = '',
    ) {
    }

    public function get_course_id(): int
    {
        return $this->course_id;
    }

    public function get_user_id(): int
    {
        return $this->user_id;
    }

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_permalink(): string
    {
        return $this->permalink;
    }

    public function get_course(): object
    {
        return $this->course;
    }
}

final class VgpcLearningProgressFixture
{
    public function __construct(private mixed $summary)
    {
    }

    public function get_summary(string $scope = 'all'): mixed
    {
        return $scope === 'all' ? $this->summary : null;
    }
}

final class VgpcLearningProviderFixture
{
    /** @param mixed $courses */
    public function __construct(
        private mixed $courses,
        private array $progress = [],
        private array $certificates = [],
    ) {
    }

    /** @var array<string, mixed> */
    public array $course_query = [];

    public int $enrolment_checks = 0;

    public function user_courses(int $user_id, array $query = []): mixed
    {
        $this->course_query = ['user_id' => $user_id, 'query' => $query];

        return $this->courses;
    }

    public function is_enrolled(int $course_id, int $user_id): bool
    {
        ++$this->enrolment_checks;

        return $user_id === 42 && $course_id !== 999;
    }

    public function course_progress(int $course_id, int $user_id): mixed
    {
        return $this->progress[$course_id] ?? null;
    }

    public function certificate_url(int $course_id, int $user_id): string
    {
        return (string) ($this->certificates[$course_id] ?? '');
    }
}

final class VgpcLearningUserFirstProviderFixture
{
    public array $access_arguments = [];

    public array $progress_arguments = [];

    public function __construct(private object $course)
    {
    }

    public function user_courses(int $user_id, array $query = []): array
    {
        return [new VgpcLearningUserCourseFixture(412, $user_id, 'enrolled', $this->course)];
    }

    public function is_enrolled(int $user_id, int $course_id): bool
    {
        $this->access_arguments = [$user_id, $course_id];

        return $user_id === 42 && $course_id === 412;
    }

    public function course_progress(int $user_id, int $course_id): mixed
    {
        $this->progress_arguments = [$user_id, $course_id];

        return new VgpcLearningProgressFixture(['completed' => 1, 'total' => 2]);
    }
}

final class VgpcLearningAccessResultProviderFixture
{
    public function __construct(
        private mixed $access,
        private object $course,
        private int $course_id,
    ) {
    }

    public function user_courses(int $user_id, array $query = []): array
    {
        return [new VgpcLearningUserCourseFixture($this->course_id, $user_id, 'enrolled', $this->course)];
    }

    public function is_enrolled(int $course_id, int $user_id): mixed
    {
        return $this->access;
    }
}

final class VgpcLearningMissingAccessProviderFixture
{
    public function __construct(private object $course)
    {
    }

    public function user_courses(int $user_id, array $query = []): array
    {
        return [new VgpcLearningUserCourseFixture(410, $user_id, 'enrolled', $this->course)];
    }
}

final class VgpcNativeMasteriyoUserCourseQueryFixture
{
    /** @var array<string, mixed> */
    public static array $query_args = [];

    /** @var array<int, mixed> */
    public static array $courses = [];

    /** @param array<string, mixed> $query_args */
    public function __construct(array $query_args = [])
    {
        self::$query_args = $query_args;
    }

    /** @return array<int, mixed> */
    public function get_user_courses(): array
    {
        return self::$courses;
    }
}

if (! function_exists('vgpc_register_native_masteriyo_fixtures')) {
    function vgpc_register_native_masteriyo_fixtures(): void
    {
        if (! class_exists('Masteriyo\\Query\\UserCourseQuery')) {
            class_alias(VgpcNativeMasteriyoUserCourseQueryFixture::class, 'Masteriyo\\Query\\UserCourseQuery');
        }

        if (! function_exists('masteriyo_is_user_enrolled_in_course')) {
            eval(<<<'PHP'
function masteriyo_is_user_enrolled_in_course($course_id, $user_id): bool
{
    return (int) $course_id === 410 && (int) $user_id === 42;
}
PHP);
        }
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(public string $code = '')
        {
        }
    }
}

final class VgpcThrowingLearningProviderFixture
{
    public function user_courses(int $user_id, array $query = []): array
    {
        throw new RuntimeException('Masteriyo unavailable.');
    }
}

final class LearningServiceTest
{
    public function test_native_masteriyo_below_the_security_minimum_fails_closed(): void
    {
        $card = (new LearningService(null, static fn (): string => '3.4.1'))->card(42);

        assert_same('unavailable', $card['availability']);
        assert_same([], $card['items']);
    }

    public function test_returns_owned_courses_with_progress_next_lesson_and_certificate(): void
    {
        assert_true(class_exists(LearningService::class));

        $first_course = new VgpcLearningCourseFixture(301, 'Selfsorg', '/kursus/selfsorg/');
        $second_course = new VgpcLearningCourseFixture(302, 'Werk en geld', '/kursus/werk-en-geld/');
        $other_course = new VgpcLearningCourseFixture(303, 'Ander kursus', '/kursus/ander/');
        $provider = new VgpcLearningProviderFixture(
            [
                new VgpcLearningUserCourseFixture(301, 42, 'enrolled', $first_course),
                new VgpcLearningUserCourseFixture(302, 42, 'completed', $second_course),
                new VgpcLearningUserCourseFixture(303, 99, 'enrolled', $other_course),
            ],
            [
                301 => new VgpcLearningProgressFixture([
                    'completed' => 2,
                    'total'     => 4,
                    'next_lesson' => [
                        'title' => 'Les 3',
                        'url'   => '/les/3/',
                    ],
                ]),
                302 => new VgpcLearningProgressFixture(['completed' => 4, 'total' => 4]),
            ],
            [302 => '/sertifikate/302/']
        );

        $card = (new LearningService($provider))->card(42);

        assert_same('learning', $card['key']);
        assert_same(AfrikaansLabels::LEARNING, $card['label']);
        assert_same('available', $card['availability']);
        assert_same(
            ['user_id' => 42, 'query' => ['status' => 'any', 'per_page' => -1]],
            $provider->course_query
        );
        assert_same(2, count($card['items']));
        assert_same('Selfsorg', $card['items'][0]['title']);
        assert_same('/kursus/selfsorg/', $card['items'][0]['course_url']);
        assert_same('enrolled', $card['items'][0]['enrollment_state']);
        assert_same(50, $card['items'][0]['completion_percentage']);
        assert_same('Les 3', $card['items'][0]['next_lesson']);
        assert_same('', $card['items'][0]['certificate_url']);
        assert_same('/sertifikate/302/', $card['items'][1]['certificate_url']);
        assert_same(100, $card['items'][1]['completion_percentage']);
        assert_same('', $card['items'][1]['next_lesson']);
        assert_true($provider->enrolment_checks >= 2);
    }

    public function test_missing_progress_is_empty_and_does_not_become_hundred_percent(): void
    {
        $course = new VgpcLearningCourseFixture(304, 'Leë vordering', '/kursus/leë-vordering/');
        $provider = new VgpcLearningProviderFixture([
            new VgpcLearningUserCourseFixture(304, 42, 'enrolled', $course),
        ]);

        $card = (new LearningService($provider))->card(42);

        assert_same('', $card['items'][0]['completion_percentage']);
        assert_same('', $card['items'][0]['completion_label']);
        assert_same('', $card['items'][0]['next_lesson']);
        assert_same('', $card['items'][0]['certificate_url']);
    }

    public function test_null_provider_access_result_is_denied(): void
    {
        $course = new VgpcLearningCourseFixture(308, 'Onbekende toegang', '/kursus/onbekende-toegang/');
        $provider = new VgpcLearningAccessResultProviderFixture(null, $course, 308);

        $card = (new LearningService($provider))->card(42);

        assert_same('available', $card['availability']);
        assert_same([], $card['items']);
    }

    public function test_wp_error_provider_access_result_is_denied(): void
    {
        $course = new VgpcLearningCourseFixture(309, 'Foutiewe toegang', '/kursus/foutiewe-toegang/');
        $provider = new VgpcLearningAccessResultProviderFixture(new WP_Error('denied'), $course, 309);

        $card = (new LearningService($provider))->card(42);

        assert_same('available', $card['availability']);
        assert_same([], $card['items']);
    }

    public function test_missing_access_provider_is_unavailable(): void
    {
        $course = new VgpcLearningCourseFixture(410, 'Geen toegangskontrole', '/kursus/geen-toegangskontrole/');
        $provider = new VgpcLearningMissingAccessProviderFixture($course);

        $card = (new LearningService($provider))->card(42);

        assert_same('unavailable', $card['availability']);
        assert_same([], $card['items']);
    }

    public function test_empty_access_returns_afrikaans_empty_card(): void
    {
        $provider = new VgpcLearningProviderFixture([]);

        $card = (new LearningService($provider))->card(42);

        assert_same(AfrikaansLabels::LEARNING, $card['label']);
        assert_same('available', $card['availability']);
        assert_same('Geen kursusse met toegang nie.', $card['summary']);
        assert_same([], $card['items']);
    }

    public function test_provider_throwing_returns_unavailable_card(): void
    {
        $card = (new LearningService(new VgpcThrowingLearningProviderFixture()))->card(42);

        assert_same('unavailable', $card['availability']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_requested_or_authenticated_user_mismatch_does_not_query_masteriyo(): void
    {
        $provider = new VgpcLearningProviderFixture([]);

        $card = (new LearningService($provider))->card(99);

        assert_same('unavailable', $card['availability']);
        assert_same([], $provider->course_query);

        vgpc_set_current_user_id(0);
        try {
            $card = (new LearningService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same('unavailable', $card['availability']);
        assert_same([], $provider->course_query);
    }

    public function test_malformed_course_result_is_unavailable(): void
    {
        $provider = new VgpcLearningProviderFixture([new stdClass()]);

        $card = (new LearningService($provider))->card(42);

        assert_same('unavailable', $card['availability']);
        assert_same([], $card['items']);
    }

    public function test_external_course_and_certificate_urls_are_not_exposed(): void
    {
        $course = new VgpcLearningCourseFixture(305, 'Onveilige skakel', 'https://ander.example/kursus/');
        $provider = new VgpcLearningProviderFixture(
            [new VgpcLearningUserCourseFixture(305, 42, 'completed', $course)],
            [305 => new VgpcLearningProgressFixture(['completed' => 1, 'total' => 1])],
            [305 => 'https://ander.example/certifikaat/']
        );

        $card = (new LearningService($provider))->card(42);

        assert_same('', $card['items'][0]['course_url']);
        assert_same('', $card['items'][0]['certificate_url']);
        assert_same([], $card['items'][0]['actions']);
    }

    public function test_native_course_progress_summary_uses_nested_total_counts_for_completion(): void
    {
        $course = new VgpcLearningCourseFixture(306, 'Voltooide vordering', '/kursus/voltooide-vordering/');
        $provider = new VgpcLearningProviderFixture(
            [new VgpcLearningUserCourseFixture(306, 42, 'enrolled', $course)],
            [306 => new VgpcLearningProgressFixture([
                'total' => [
                    'completed' => 4,
                    'total'     => 4,
                ],
            ])],
            [306 => '/sertifikate/306/']
        );

        $card = (new LearningService($provider))->card(42);

        assert_same(100, $card['items'][0]['completion_percentage']);
        assert_same('/sertifikate/306/', $card['items'][0]['certificate_url']);
    }

    public function test_incomplete_course_uses_native_continue_course_url(): void
    {
        $course = new VgpcLearningContinuingCourseFixture(
            307,
            'Gaan voort',
            '/kursus/gaan-voort/',
            '/kursus/gaan-voort/les/2/'
        );
        $provider = new VgpcLearningProviderFixture(
            [new VgpcLearningUserCourseFixture(307, 42, 'enrolled', $course)],
            [307 => new VgpcLearningProgressFixture([
                'total' => [
                    'completed' => 1,
                    'total'     => 2,
                ],
            ])]
        );

        $card = (new LearningService($provider))->card(42);

        assert_same('/kursus/gaan-voort/les/2/', $card['items'][0]['next_lesson_url']);
        assert_same('/kursus/gaan-voort/les/2/', $card['items'][0]['actions']['next-lesson']['url']);
        assert_same(1, $course->continue_calls);
    }

    public function test_native_course_query_requests_all_owned_courses(): void
    {
        $course = new VgpcLearningCourseFixture(410, 'Alle kursusse', '/kursus/alle-kursusse/');
        VgpcNativeMasteriyoUserCourseQueryFixture::$courses = [
            new VgpcLearningUserCourseFixture(410, 42, 'enrolled', $course),
        ];
        vgpc_register_native_masteriyo_fixtures();

        $card = (new LearningService())->card(42);

        assert_same(
            ['user_id' => 42, 'status' => 'any', 'per_page' => -1],
            VgpcNativeMasteriyoUserCourseQueryFixture::$query_args
        );
        assert_same('available', $card['availability']);
        assert_same('410', $card['items'][0]['course_id']);
    }

    public function test_provider_user_first_course_methods_keep_user_and_course_order(): void
    {
        $course = new VgpcLearningCourseFixture(412, 'Gebruiker eerste', '/kursus/gebruiker-eerste/');
        $provider = new VgpcLearningUserFirstProviderFixture($course);

        $card = (new LearningService($provider))->card(42);

        assert_same('available', $card['availability']);
        assert_same([42, 412], $provider->access_arguments);
        assert_same([42, 412], $provider->progress_arguments);
        assert_same(50, $card['items'][0]['completion_percentage']);
    }
}
