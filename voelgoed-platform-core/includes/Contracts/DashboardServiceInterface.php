<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Contracts;

/**
 * Contract for a dashboard provider that returns one normalized summary card.
 */
interface DashboardServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function card(int $user_id): array;
}
