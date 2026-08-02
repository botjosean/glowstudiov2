<?php

namespace App\Support;

class MockData
{
    public static function homeStats(): array
    {
        return ['services' => 12, 'providers' => 5];
    }

    public static function homeServices(): array
    {
        return [
            ['id' => 1, 'icon' => 'scissors', 'name' => 'VIP Haircut + Beard', 'duration' => '60 min', 'providersCount' => 3, 'price' => 40],
            ['id' => 2, 'icon' => 'scissors', 'name' => 'Haircut', 'duration' => '40 min', 'providersCount' => 4, 'price' => 30],
            ['id' => 3, 'icon' => 'sparkles', 'name' => 'Beard grooming', 'duration' => '25 min', 'providersCount' => 2, 'price' => 18],
            ['id' => 4, 'icon' => 'smile', 'name' => 'Kids haircut', 'duration' => '30 min', 'providersCount' => 3, 'price' => 22],
        ];
    }

    public static function providers(): array
    {
        return [
            [
                'id' => 1,
                'slug' => 'pati',
                'name' => 'Pati',
                'bio' => 'Precision fades and beard grooming.',
                'photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=200',
                'servicesCount' => 4,
                'availableNow' => true,
                'mobile' => true,
            ],
            [
                'id' => 2,
                'slug' => 'miguel',
                'name' => 'Miguel',
                'bio' => 'Master barber. Classic and modern cuts.',
                'photo' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=200',
                'servicesCount' => 3,
                'availableNow' => true,
                'mobile' => false,
            ],
            [
                'id' => 3,
                'slug' => 'andres',
                'name' => 'Andrés',
                'bio' => 'Beard and classic shaving specialist.',
                'photo' => 'https://images.unsplash.com/photo-1583864697784-a0efc8379f70?auto=format&fit=crop&q=80&w=200',
                'servicesCount' => 2,
                'availableNow' => false,
                'mobile' => false,
            ],
            [
                'id' => 4,
                'slug' => 'luis',
                'name' => 'Luis',
                'bio' => 'Creative designs and kids haircuts.',
                'photo' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=200',
                'servicesCount' => 2,
                'availableNow' => false,
                'mobile' => false,
            ],
            [
                'id' => 5,
                'slug' => 'daniela',
                'name' => 'Daniela',
                'bio' => 'Color, styling and treatments.',
                'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=200',
                'servicesCount' => 5,
                'availableNow' => false,
                'mobile' => true,
            ],
        ];
    }

    public static function providerProfile(string $slug): ?array
    {
        if ($slug !== 'pati') {
            return null;
        }

        return [
            'slug' => 'pati',
            'name' => 'Pati Barber',
            'bio' => 'Professional barber with more than eight years of experience. Specialist in precision fades, creative designs and beard grooming. Punctuality and style guaranteed.',
            'availableNow' => true,
            'bannerPhoto' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&q=80&w=800',
            'avatarPhoto' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=400',
            'location' => [
                'title' => 'Mobile service · available now',
                'subtitle' => 'At your location — Brickell & Wynwood area',
            ],
            'social' => [
                'whatsapp' => '#',
                'instagram' => '#',
                'tiktok' => '#',
                'facebook' => '#',
            ],
            'gallery' => [
                'https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&q=80&w=400',
                'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&q=80&w=400',
                'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&q=80&w=400',
            ],
            'services' => [
                ['id' => 1, 'icon' => 'scissors', 'name' => 'VIP Haircut + Beard', 'duration' => '60 min', 'price' => 40],
                ['id' => 2, 'icon' => 'scissors', 'name' => 'Haircut', 'duration' => '40 min', 'price' => 30],
            ],
        ];
    }

    public static function findService(string $slug, int $serviceId): ?array
    {
        $profile = self::providerProfile($slug);

        if (! $profile) {
            return null;
        }

        foreach ($profile['services'] as $service) {
            if ($service['id'] === $serviceId) {
                return $service;
            }
        }

        return null;
    }

    public static function appointments(): array
    {
        return [
            ['id' => 1, 'clientName' => 'John Smith', 'clientPhone' => '(305) 555-0199', 'service' => 'VIP Haircut + Beard', 'provider' => 'Pati Barber', 'duration' => '60 min', 'price' => 40, 'status' => 'confirmed', 'isToday' => true, 'dateLabel' => 'Today, Mon Jul 13 · 01:30 PM', 'dateGroupLabel' => 'Hoy · Lun 13 Jul', 'timeLabel' => '01:30 PM'],
            ['id' => 2, 'clientName' => 'Marcus Lee', 'clientPhone' => '(305) 555-0122', 'service' => 'Haircut', 'provider' => 'Pati Barber', 'duration' => '40 min', 'price' => 30, 'status' => 'confirmed', 'isToday' => true, 'dateLabel' => 'Today, Mon Jul 13 · 03:00 PM', 'dateGroupLabel' => 'Hoy · Lun 13 Jul', 'timeLabel' => '03:00 PM'],
            ['id' => 3, 'clientName' => 'Jane Doe', 'clientPhone' => '(305) 555-0123', 'service' => 'Skin Fade', 'provider' => 'Pati Barber', 'duration' => '45 min', 'price' => 35, 'status' => 'pending', 'isToday' => false, 'dateLabel' => 'Mar 14 Jul · 10:45 AM', 'dateGroupLabel' => null, 'timeLabel' => '10:45 AM'],
            ['id' => 4, 'clientName' => 'Marcus Lee', 'clientPhone' => '(305) 555-0122', 'service' => 'Haircut', 'provider' => 'Pati Barber', 'duration' => '40 min', 'price' => 30, 'status' => 'pending', 'isToday' => false, 'dateLabel' => 'Mar 14 Jul · 11:45 AM', 'dateGroupLabel' => null, 'timeLabel' => '11:45 AM'],
            ['id' => 5, 'clientName' => 'Daniel Ortiz', 'clientPhone' => '(305) 555-0177', 'service' => 'VIP Haircut + Beard', 'provider' => 'Pati Barber', 'duration' => '60 min', 'price' => 40, 'status' => 'pending', 'isToday' => false, 'dateLabel' => 'Mar 14 Jul · 4:00 PM', 'dateGroupLabel' => null, 'timeLabel' => '4:00 PM'],
            ['id' => 6, 'clientName' => 'Alex Rivera', 'clientPhone' => '(305) 555-0111', 'service' => 'Haircut', 'provider' => 'Pati Barber', 'duration' => '40 min', 'price' => 30, 'status' => 'closed', 'isToday' => false, 'dateLabel' => 'Fri 10 Jul · 02:00 PM', 'dateGroupLabel' => null, 'timeLabel' => '02:00 PM'],
            ['id' => 7, 'clientName' => 'Tom Wright', 'clientPhone' => '(305) 555-0144', 'service' => 'Beard grooming', 'provider' => 'Pati Barber', 'duration' => '25 min', 'price' => 18, 'status' => 'closed', 'isToday' => false, 'dateLabel' => 'Thu 9 Jul · 11:00 AM', 'dateGroupLabel' => null, 'timeLabel' => '11:00 AM'],
            ['id' => 8, 'clientName' => 'Sam Cole', 'clientPhone' => '(305) 555-0155', 'service' => 'VIP Haircut + Beard', 'provider' => 'Pati Barber', 'duration' => '60 min', 'price' => 40, 'status' => 'closed', 'isToday' => false, 'dateLabel' => 'Wed 8 Jul · 09:30 AM', 'dateGroupLabel' => null, 'timeLabel' => '09:30 AM'],
            ['id' => 9, 'clientName' => 'Chris Park', 'clientPhone' => '(305) 555-0166', 'service' => 'Haircut', 'provider' => 'Pati Barber', 'duration' => '40 min', 'price' => 30, 'status' => 'cancelled', 'isToday' => false, 'dateLabel' => 'Tue 7 Jul · 04:00 PM', 'dateGroupLabel' => null, 'timeLabel' => '04:00 PM'],
            ['id' => 10, 'clientName' => 'Nina Ruiz', 'clientPhone' => '(305) 555-0188', 'service' => 'Kids haircut', 'provider' => 'Pati Barber', 'duration' => '30 min', 'price' => 22, 'status' => 'cancelled', 'isToday' => false, 'dateLabel' => 'Mon 6 Jul · 01:00 PM', 'dateGroupLabel' => null, 'timeLabel' => '01:00 PM'],
        ];
    }

    public static function adminServices(): array
    {
        return [
            ['id' => 1, 'name' => 'VIP Haircut + Beard', 'durationMinutes' => 60, 'price' => 40, 'category' => 'fade'],
            ['id' => 2, 'name' => 'Haircut', 'durationMinutes' => 40, 'price' => 30, 'category' => 'classic'],
        ];
    }

    public static function schedule(): array
    {
        return [
            'workStart' => 9 * 60,
            'workEnd' => 20 * 60,
            'lunchStart' => 13 * 60,
            'lunchEnd' => 14 * 60,
            'bufferMinutes' => 15,
        ];
    }

    public static function adminProfile(): array
    {
        return [
            'username' => 'patib',
            'publicName' => 'Pati Barber',
            'phone' => '+1 305 555-0142',
            'email' => 'pati@glowstudiovip.com',
            'bio' => 'Professional barber with more than eight years of experience. Specialist in precision fades, creative designs and beard grooming.',
            'bannerPhoto' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&q=80&w=800',
            'avatarPhoto' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=400',
            'gallery' => [
                'https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&q=80&w=400',
                'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&q=80&w=400',
                'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&q=80&w=400',
            ],
            'maxGallery' => 6,
        ];
    }
}
