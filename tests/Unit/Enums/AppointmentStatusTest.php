<?php

namespace Tests\Unit\Enums;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    /**
     * The full 4x4 transition matrix. Only pending->{confirmed,cancelled}
     * and confirmed->{cancelled,closed} are legal; everything else
     * (including every transition out of a terminal state, and every
     * self-transition) is not.
     *
     * @return iterable<string, array{0: AppointmentStatus, 1: AppointmentStatus, 2: bool}>
     */
    public static function transitions(): iterable
    {
        yield 'pending -> pending' => [AppointmentStatus::Pending, AppointmentStatus::Pending, false];
        yield 'pending -> confirmed' => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, true];
        yield 'pending -> cancelled' => [AppointmentStatus::Pending, AppointmentStatus::Cancelled, true];
        yield 'pending -> closed' => [AppointmentStatus::Pending, AppointmentStatus::Closed, false];

        yield 'confirmed -> pending' => [AppointmentStatus::Confirmed, AppointmentStatus::Pending, false];
        yield 'confirmed -> confirmed' => [AppointmentStatus::Confirmed, AppointmentStatus::Confirmed, false];
        yield 'confirmed -> cancelled' => [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, true];
        yield 'confirmed -> closed' => [AppointmentStatus::Confirmed, AppointmentStatus::Closed, true];

        yield 'cancelled -> pending' => [AppointmentStatus::Cancelled, AppointmentStatus::Pending, false];
        yield 'cancelled -> confirmed' => [AppointmentStatus::Cancelled, AppointmentStatus::Confirmed, false];
        yield 'cancelled -> cancelled' => [AppointmentStatus::Cancelled, AppointmentStatus::Cancelled, false];
        yield 'cancelled -> closed' => [AppointmentStatus::Cancelled, AppointmentStatus::Closed, false];

        yield 'closed -> pending' => [AppointmentStatus::Closed, AppointmentStatus::Pending, false];
        yield 'closed -> confirmed' => [AppointmentStatus::Closed, AppointmentStatus::Confirmed, false];
        yield 'closed -> cancelled' => [AppointmentStatus::Closed, AppointmentStatus::Cancelled, false];
        yield 'closed -> closed' => [AppointmentStatus::Closed, AppointmentStatus::Closed, false];
    }

    #[DataProvider('transitions')]
    public function test_transition_matrix(AppointmentStatus $from, AppointmentStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }
}
