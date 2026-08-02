<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Closed = 'closed';

    public function blocksSlot(): bool
    {
        return match ($this) {
            self::Pending, self::Confirmed => true,
            self::Cancelled, self::Closed => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Closed => true,
            self::Pending, self::Confirmed => false,
        };
    }

    /**
     * Legal admin-driven transitions. Pending -> Closed is deliberately
     * absent: only the scheduled command closes appointments, and only
     * from Confirmed (a Pending appointment left unactioned stays Pending).
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($target, [self::Cancelled, self::Closed], true),
            self::Cancelled, self::Closed => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function blocking(): array
    {
        return [self::Pending->value, self::Confirmed->value];
    }
}
