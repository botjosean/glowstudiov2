<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Ten appointments covering all four statuses, seeded relative to
     * "today" (in the provider's timezone) so the admin "Today" tab is
     * always populated, unlike the mock's fixed July 2026 dates.
     *
     * Rows for Jane Doe, Tom Wright and Nina Ruiz reference services pati
     * doesn't own (service_id null, snapshot only) — this exercises the
     * deleted/foreign-service path end to end. The mock's own "today 13:30"
     * appointment fell inside pati's 13:00-14:00 lunch block, so it's moved
     * to 11:00 here to keep the seeded data internally consistent.
     */
    public function run(): void
    {
        $pati = Provider::where('slug', 'pati')->firstOrFail();
        $vip = $pati->services()->where('name', 'VIP Haircut + Beard')->firstOrFail();
        $haircut = $pati->services()->where('name', 'Haircut')->firstOrFail();

        $today = $pati->currentTime()->startOfDay();

        $rows = [
            ['client' => 'John Smith', 'phone' => '3055550199', 'service' => $vip, 'name' => 'VIP Haircut + Beard', 'duration' => 60, 'price' => 40, 'status' => AppointmentStatus::Confirmed, 'at' => $today->addHours(11)],
            ['client' => 'Marcus Lee', 'phone' => '3055550122', 'service' => $haircut, 'name' => 'Haircut', 'duration' => 40, 'price' => 30, 'status' => AppointmentStatus::Confirmed, 'at' => $today->addHours(15)],
            ['client' => 'Jane Doe', 'phone' => '3055550123', 'service' => null, 'name' => 'Skin Fade', 'duration' => 45, 'price' => 35, 'status' => AppointmentStatus::Pending, 'at' => $today->addDay()->addHours(10)->addMinutes(45)],
            ['client' => 'Marcus Lee', 'phone' => '3055550122', 'service' => $haircut, 'name' => 'Haircut', 'duration' => 40, 'price' => 30, 'status' => AppointmentStatus::Pending, 'at' => $today->addDay()->addHours(11)->addMinutes(45)],
            ['client' => 'Daniel Ortiz', 'phone' => '3055550177', 'service' => $vip, 'name' => 'VIP Haircut + Beard', 'duration' => 60, 'price' => 40, 'status' => AppointmentStatus::Pending, 'at' => $today->addDay()->addHours(16)],
            ['client' => 'Alex Rivera', 'phone' => '3055550111', 'service' => $haircut, 'name' => 'Haircut', 'duration' => 40, 'price' => 30, 'status' => AppointmentStatus::Closed, 'at' => $today->subDays(3)->addHours(14)],
            ['client' => 'Tom Wright', 'phone' => '3055550144', 'service' => null, 'name' => 'Beard grooming', 'duration' => 25, 'price' => 18, 'status' => AppointmentStatus::Closed, 'at' => $today->subDays(4)->addHours(11)],
            ['client' => 'Sam Cole', 'phone' => '3055550155', 'service' => $vip, 'name' => 'VIP Haircut + Beard', 'duration' => 60, 'price' => 40, 'status' => AppointmentStatus::Closed, 'at' => $today->subDays(5)->addHours(9)->addMinutes(30)],
            ['client' => 'Chris Park', 'phone' => '3055550166', 'service' => $haircut, 'name' => 'Haircut', 'duration' => 40, 'price' => 30, 'status' => AppointmentStatus::Cancelled, 'at' => $today->subDays(6)->addHours(16)],
            ['client' => 'Nina Ruiz', 'phone' => '3055550188', 'service' => null, 'name' => 'Kids haircut', 'duration' => 30, 'price' => 22, 'status' => AppointmentStatus::Cancelled, 'at' => $today->subDays(7)->addHours(13)],
        ];

        foreach ($rows as $row) {
            /** @var CarbonImmutable $startsAt */
            // Eloquent does not convert to UTC on save — it stores whatever
            // wall-clock string the Carbon instance currently formats to, so
            // local times must be converted explicitly before saving.
            $startsAt = $row['at']->utc();

            Appointment::create([
                'provider_id' => $pati->id,
                'service_id' => $row['service']?->id,
                'client_name' => $row['client'],
                'client_phone' => $row['phone'],
                'service_name' => $row['name'],
                'duration_minutes' => $row['duration'],
                'price' => $row['price'],
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($row['duration']),
                'status' => $row['status']->value,
            ]);
        }
    }
}
