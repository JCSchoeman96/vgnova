<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\TicketService;

final class VgpcTicketOrderFixture
{
    public function __construct(
        private int $id,
        private mixed $author,
        private string $email = '',
        private string $order_key = '',
        private string $status = '',
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_user_id(): mixed
    {
        return $this->author;
    }

    public function get_order_key(): string
    {
        return $this->order_key;
    }

    public function get_buyer_email(): string
    {
        return $this->email;
    }

    public function get_status(): string
    {
        return $this->status;
    }
}

final class VgpcTicketInstanceFixture
{
    public function __construct(
        private int $id,
        private int $order_id,
        private int $event_id,
        private string $owner_email = '',
        private string $status = 'valid',
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_order_id(): int
    {
        return $this->order_id;
    }

    public function get_event_id(): int
    {
        return $this->event_id;
    }

    public function get_owner_email(): string
    {
        return $this->owner_email;
    }

    public function get_status(): string
    {
        return $this->status;
    }
}

final class VgpcTicketEventFixture
{
    public function __construct(
        private int $id,
        private string $title,
        private string $start,
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

    public function get_start_date(): string
    {
        return $this->start;
    }

    public function get_permalink(): string
    {
        return $this->permalink;
    }
}

final class VgpcLocalizedMultiDayTicketEventFixture
{
    public object $details;

    public function __construct()
    {
        $this->details = (object) [
            'post_title'          => 'Meerdaggeleentheid',
            'event_date_time'     => '2099-08-15 09:00:00',
            'event_end_date_time' => '2099-08-17 17:00:00',
        ];
    }

    public function get_event_date(): string
    {
        return 'Saterdag 15 Augustus 2099 tot Maandag 17 Augustus 2099';
    }
}

final class VgpcTicketProviderFixture
{
    /** @param mixed $orders */
    public function __construct(
        private mixed $orders,
        private array $tickets,
        private array $events,
        private array $downloads = [],
    ) {
    }

    public int $requested_user_id = 0;

    public string $requested_status = '';

    public function orders(object $user): mixed
    {
        $this->requested_user_id = (int) ($user->ID ?? 0);

        return $this->orders;
    }

    public function ticket_ids(int $order_id, string $post_status): array
    {
        $this->requested_status = $post_status;

        return $this->tickets[$order_id] ?? [];
    }

    public function ticket(int $ticket_id): mixed
    {
        return $this->tickets['instances'][$ticket_id] ?? null;
    }

    public function event(int $event_id): mixed
    {
        return $this->events[$event_id] ?? null;
    }

    public function download_url(int $ticket_id): string
    {
        return (string) ($this->downloads[$ticket_id] ?? '');
    }

    public function order_url(object $order): string
    {
        $order_id = method_exists($order, 'get_id') ? $order->get_id() : ($order->ID ?? 0);

        return '/my-account/tickera-order/' . $order_id . '/';
    }
}

final class VgpcThrowingTicketProviderFixture
{
    public function orders(object $user): array
    {
        throw new RuntimeException('Tickera unavailable.');
    }
}

final class VgpcNativeTickeraOrdersFixture
{
    public static mixed $requested_user_id = null;

    public static string $requested_user_email = '';

    public static int $calls = 0;

    public static function get_user_orders(object $user): array
    {
        self::$requested_user_id = (int) ($user->ID ?? 0);
        self::$requested_user_email = (string) ($user->user_email ?? '');
        ++self::$calls;

        return [
            (object) [
                'ID'          => 901,
                'post_author' => self::$requested_user_id,
                'post_status' => 'order_paid',
            ],
        ];
    }

    public static function get_tickets_ids(int $order_id, string $post_status): array
    {
        return $order_id === 901 && $post_status === 'publish' ? [902] : [];
    }
}

final class VgpcNativeTickeraTicketFixture
{
    public object $details;

    public function __construct(int $ticket_id)
    {
        $this->details = (object) [
            'post_parent' => 901,
            'event_id'    => 903,
            'status'      => 'valid',
        ];
    }
}

final class VgpcNativeTickeraEventFixture
{
    public object $details;

    public function __construct(int $event_id)
    {
        $this->details = (object) [
            'post_title'      => 'Native Tickera-geleentheid',
            'event_date_time' => '2099-07-01 18:00:00',
        ];
    }
}

if (! function_exists('vgpc_register_native_tickera_fixtures')) {
    function vgpc_register_native_tickera_fixtures(): void
    {
        $aliases = [
            VgpcNativeTickeraOrdersFixture::class => 'Tickera\\TC_Orders',
            VgpcNativeTickeraTicketFixture::class => 'Tickera\\TC_Ticket_Instance',
            VgpcNativeTickeraEventFixture::class  => 'Tickera\\TC_Event',
        ];

        foreach ($aliases as $source => $target) {
            if (! class_exists($target)) {
                class_alias($source, $target);
            }
        }
    }
}

final class TicketServiceTest
{
    public function test_returns_owned_tickets_grouped_by_event_date_with_native_actions(): void
    {
        assert_true(class_exists(TicketService::class));

        $upcoming_event = new VgpcTicketEventFixture(701, 'Voelgoed Live', '2099-05-01 19:00:00', '/events/voelgoed-live/');
        $past_event = new VgpcTicketEventFixture(702, 'Vrouedag', '2020-08-09 10:00:00', '/events/vrouedag/');
        $provider = new VgpcTicketProviderFixture(
            [
                new VgpcTicketOrderFixture(601, 42, 'user@example.test', 'order-key-601'),
                new VgpcTicketOrderFixture(602, 99, 'other@example.test', 'order-key-602'),
            ],
            [
                601 => [801, 802],
                602 => [803],
                'instances' => [
                    801 => new VgpcTicketInstanceFixture(801, 601, 701, 'user@example.test'),
                    802 => new VgpcTicketInstanceFixture(802, 601, 702, 'user@example.test', 'used'),
                    803 => new VgpcTicketInstanceFixture(803, 602, 701, 'other@example.test'),
                ],
            ],
            [701 => $upcoming_event, 702 => $past_event],
            [801 => '/tickera/ticket/801/?sig=abc', 802 => '/tickera/ticket/802/?sig=def']
        );

        $card = (new TicketService($provider))->card(42);

        assert_same('tickets', $card['key']);
        assert_same(AfrikaansLabels::TICKETS, $card['label']);
        assert_same('active', $card['state']);
        assert_same('upcoming', $card['items'][0]['group']);
        assert_same('past', $card['items'][1]['group']);
        assert_same('Voelgoed Live', $card['items'][0]['event_title']);
        assert_same('801', $card['items'][0]['ticket_id']);
        assert_same('used', $card['items'][1]['status']);
        assert_same('publish', $provider->requested_status);
        assert_same('/tickera/ticket/801/?sig=abc', $card['items'][0]['actions']['download']['url']);
        assert_same('/tickera/ticket/801/?sig=abc', $card['items'][0]['actions']['qr']['url']);
        assert_same('/my-account/tickera-order/601/', $card['items'][0]['actions']['order']['url']);
    }

    public function test_empty_ticket_access_uses_the_afrikaans_empty_copy(): void
    {
        $provider = new VgpcTicketProviderFixture([], [], []);

        $card = (new TicketService($provider))->card(42);

        assert_same('empty', $card['state']);
        assert_same('Geen komende kaartjies nie.', $card['summary']);
        assert_same([], $card['items']);
    }

    public function test_foreign_orders_and_ticket_email_mismatches_are_omitted(): void
    {
        vgpc_set_current_user_email('user@example.test');
        $provider = new VgpcTicketProviderFixture(
            [new VgpcTicketOrderFixture(603, 42, 'user@example.test')],
            [
                603 => [804, 805],
                'instances' => [
                    804 => new VgpcTicketInstanceFixture(804, 603, 701, 'other@example.test'),
                    805 => new VgpcTicketInstanceFixture(805, 999, 701, 'user@example.test'),
                ],
            ],
            [701 => new VgpcTicketEventFixture(701, 'Moet nie verskyn nie', '2099-05-01', '/events/hidden/')]
        );

        $card = (new TicketService($provider))->card(42);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        vgpc_set_current_user_email('');
    }

    public function test_email_history_orders_require_an_exact_authenticated_email_match(): void
    {
        vgpc_set_current_user_email('user@example.test');
        $provider = new VgpcTicketProviderFixture(
            [
                new VgpcTicketOrderFixture(607, null, 'user@example.test'),
                new VgpcTicketOrderFixture(608, null, 'USER@EXAMPLE.TEST'),
                new VgpcTicketOrderFixture(609, null, 'other@example.test'),
                new VgpcTicketOrderFixture(610, null, ''),
            ],
            [
                607 => [807],
                608 => [808],
                609 => [809],
                610 => [810],
                'instances' => [
                    807 => new VgpcTicketInstanceFixture(807, 607, 701, 'user@example.test'),
                    808 => new VgpcTicketInstanceFixture(808, 608, 701, 'user@example.test'),
                    809 => new VgpcTicketInstanceFixture(809, 609, 701, 'other@example.test'),
                    810 => new VgpcTicketInstanceFixture(810, 610, 701, 'user@example.test'),
                ],
            ],
            [701 => new VgpcTicketEventFixture(701, 'E-posgeskiedenis', '2099-05-01', '/events/email-history/')]
        );

        $card = (new TicketService($provider))->card(42);

        assert_same('active', $card['state']);
        assert_same(2, count($card['items']));
        assert_same('807', $card['items'][0]['ticket_id']);
        assert_same('808', $card['items'][1]['ticket_id']);
        vgpc_set_current_user_email('');
    }

    public function test_nested_tickera_details_allow_matching_email_despite_foreign_author(): void
    {
        vgpc_set_current_user_email('nested@example.test');
        vgpc_set_permalink(703, '/events/nested/');

        $matching_order = (object) [
            'ID'          => 611,
            'post_author' => 99,
            'post_name'   => 'nested-order-611',
            'details'     => (object) [
                'tc_cart_info' => [
                    'buyer_data' => ['email_post_meta' => 'nested@example.test'],
                ],
            ],
        ];
        $mismatched_order = (object) [
            'ID'          => 612,
            'post_author' => 99,
            'details'     => (object) [
                'tc_cart_info' => [
                    'buyer_data' => ['email_post_meta' => 'other@example.test'],
                ],
            ],
        ];
        $missing_email_order = (object) [
            'ID'          => 613,
            'post_author' => 99,
            'details'     => (object) ['tc_cart_info' => ['buyer_data' => []]],
        ];
        $provider = new VgpcTicketProviderFixture(
            [$matching_order, $mismatched_order, $missing_email_order],
            [
                611 => [811],
                612 => [812],
                613 => [813],
                'instances' => [
                    811 => (object) [
                        'details' => (object) [
                            'post_parent' => 611,
                            'event_id'    => 703,
                            'owner_email' => 'nested@example.test',
                            'post_status' => 'valid',
                        ],
                    ],
                    812 => (object) [
                        'details' => (object) [
                            'post_parent' => 612,
                            'event_id'    => 703,
                            'owner_email' => 'other@example.test',
                            'post_status' => 'valid',
                        ],
                    ],
                    813 => (object) [
                        'details' => (object) [
                            'post_parent' => 613,
                            'event_id'    => 703,
                            'owner_email' => 'nested@example.test',
                            'post_status' => 'valid',
                        ],
                    ],
                ],
            ],
            [703 => (object) [
                'details' => (object) [
                    'post_title'       => 'Geneste Tickera-geleentheid',
                    'event_date_time'  => '2099-06-01 18:00:00',
                ],
            ]],
            [811 => '/tickera/ticket/811/?sig=nested']
        );

        $card = (new TicketService($provider))->card(42);

        assert_same('active', $card['state']);
        assert_same(1, count($card['items']));
        assert_same('Geneste Tickera-geleentheid', $card['items'][0]['event_title']);
        assert_same('2099-06-01', $card['items'][0]['event_date']);
        assert_same('/tickera/ticket/811/?sig=nested', $card['items'][0]['actions']['download']['url']);
        assert_same('/tickera/ticket/811/?sig=nested', $card['items'][0]['actions']['qr']['url']);
        vgpc_set_current_user_email('');
    }

    public function test_raw_tickera_event_start_date_drives_grouping_before_localized_getter(): void
    {
        $provider = new VgpcTicketProviderFixture(
            [new VgpcTicketOrderFixture(614, 42)],
            [
                614 => [814],
                'instances' => [814 => new VgpcTicketInstanceFixture(814, 614, 708)],
            ],
            [708 => new VgpcLocalizedMultiDayTicketEventFixture()]
        );

        $card = (new TicketService($provider))->card(42);

        assert_same('active', $card['state']);
        assert_same('upcoming', $card['items'][0]['group']);
        assert_same('2099-08-15', $card['items'][0]['event_date']);
    }

    public function test_ticket_status_comes_from_parent_order_and_maps_order_states(): void
    {
        $provider = new VgpcTicketProviderFixture(
            [
                (object) [
                    'ID'          => 620,
                    'post_author' => 42,
                    'details'     => (object) ['post_status' => 'order_paid'],
                ],
                new VgpcTicketOrderFixture(621, 42, '', 'order-key-621', 'order_cancelled'),
                new VgpcTicketOrderFixture(622, 42, '', 'order-key-622', 'order_refunded'),
            ],
            [
                620 => [820],
                621 => [821],
                622 => [822],
                'instances' => [
                    820 => (object) [
                        'details' => (object) [
                            'post_parent' => 620,
                            'event_id'    => 705,
                            'post_status' => 'valid',
                        ],
                    ],
                    821 => new VgpcTicketInstanceFixture(821, 621, 706, '', 'valid'),
                    822 => new VgpcTicketInstanceFixture(822, 622, 707, '', 'valid'),
                ],
            ],
            [
                705 => new VgpcTicketEventFixture(705, 'Betaalde geleentheid', '2099-06-02', '/events/betaal/'),
                706 => new VgpcTicketEventFixture(706, 'Gekanselleerde geleentheid', '2099-06-03', '/events/gekanselleer/'),
                707 => new VgpcTicketEventFixture(707, 'Terugbetaalde geleentheid', '2099-06-04', '/events/terugbetaal/')
            ]
        );

        $card = (new TicketService($provider))->card(42);

        assert_same('order_paid', $card['items'][0]['status']);
        assert_same('Betaal', $card['items'][0]['status_label']);
        assert_same('order_cancelled', $card['items'][1]['status']);
        assert_same('Gekanselleer', $card['items'][1]['status_label']);
        assert_same('order_refunded', $card['items'][2]['status']);
        assert_same('Terugbetaal', $card['items'][2]['status_label']);
    }

    public function test_throwing_or_malformed_provider_returns_unavailable_card(): void
    {
        $card = (new TicketService(new VgpcThrowingTicketProviderFixture()))->card(42);
        assert_same('unavailable', $card['state']);

        $malformed = new VgpcTicketProviderFixture(new stdClass(), [], []);
        $card = (new TicketService($malformed))->card(42);
        assert_same('unavailable', $card['state']);
    }

    public function test_requested_or_authenticated_user_mismatch_does_not_query_tickera(): void
    {
        $provider = new VgpcTicketProviderFixture([], [], []);

        $card = (new TicketService($provider))->card(99);
        assert_same('empty', $card['state']);
        assert_same(0, $provider->requested_user_id);

        vgpc_set_current_user_id(0);
        try {
            $card = (new TicketService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same('empty', $card['state']);
        assert_same(0, $provider->requested_user_id);
    }

    public function test_external_download_and_order_urls_are_omitted(): void
    {
        $provider = new VgpcTicketProviderFixture(
            [new VgpcTicketOrderFixture(604, 42)],
            [
                604 => [806],
                'instances' => [806 => new VgpcTicketInstanceFixture(806, 604, 704)],
            ],
            [704 => new VgpcTicketEventFixture(704, 'Onveilige geleentheid', '2099-05-01', 'https://ander.example/event/')],
            [806 => 'https://ander.example/ticket/806/']
        );

        $card = (new TicketService($provider))->card(42);

        assert_false(isset($card['items'][0]['actions']['download']));
        assert_false(isset($card['items'][0]['actions']['qr']));
        assert_true(isset($card['items'][0]['actions']['order']));
        assert_same('', $card['items'][0]['event_url']);
    }

    public function test_native_tickera_order_query_receives_authenticated_user_id(): void
    {
        vgpc_set_current_user_email('native@example.test');
        vgpc_register_native_tickera_fixtures();

        $card = (new TicketService())->card(42);

        assert_same(42, VgpcNativeTickeraOrdersFixture::$requested_user_id);
        assert_same('native@example.test', VgpcNativeTickeraOrdersFixture::$requested_user_email);
        assert_same(1, VgpcNativeTickeraOrdersFixture::$calls);
        assert_same('active', $card['state']);
        assert_same('902', $card['items'][0]['ticket_id']);
        assert_same('order_paid', $card['items'][0]['status']);
        vgpc_set_current_user_email('');
    }
}
