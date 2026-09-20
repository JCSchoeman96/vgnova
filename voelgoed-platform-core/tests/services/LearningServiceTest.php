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

final class VgpcThrowingLearningProviderFixture
{
    public function user_courses(int $user_id, array $query = []): array
    {
        throw new RuntimeException('Masteriyo unavailable.');
    }
}

final class LearningServiceTest
{
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
        assert_same('active', $card['state']);
        assert_same(['user_id' => 42, 'query' => ['status' => 'any']], $provider->course_query);
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

    public function test_empty_access_returns_afrikaans_empty_card(): void
    {
        $provider = new VgpcLearningProviderFixture([]);

        $card = (new LearningService($provider))->card(42);

        assert_same(AfrikaansLabels::LEARNING, $card['label']);
        assert_same('empty', $card['state']);
        assert_same('Geen kursusse met toegang nie.', $card['summary']);
        assert_same([], $card['items']);
    }

    public function test_provider_throwing_returns_unavailable_card(): void
    {
        $card = (new LearningService(new VgpcThrowingLearningProviderFixture()))->card(42);

        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_requested_or_authenticated_user_mismatch_does_not_query_masteriyo(): void
    {
        $provider = new VgpcLearningProviderFixture([]);

        $card = (new LearningService($provider))->card(99);

        assert_same('empty', $card['state']);
        assert_same([], $provider->course_query);

        vgpc_set_current_user_id(0);
        try {
            $card = (new LearningService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same('empty', $card['state']);
        assert_same([], $provider->course_query);
    }

    public function test_malformed_course_result_is_unavailable(): void
    {
        $provider = new VgpcLearningProviderFixture([new stdClass()]);

        $card = (new LearningService($provider))->card(42);

        assert_same('unavailable', $card['state']);
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
}
