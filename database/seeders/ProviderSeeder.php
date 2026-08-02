<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\Provider;
use App\Models\ProviderPhoto;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Created in this exact order (pati, miguel, andres, luis, daniela) so
     * their auto-increment ids reproduce the mock's provider list ordering
     * once PublicController orders by is_available_now desc, id asc.
     */
    public function run(): void
    {
        $types = ServiceType::query()->get()->keyBy('slug');

        $pati = $this->createProvider(
            username: 'patib',
            email: 'pati@glowstudiovip.com',
            phone: '3055550142',
            slug: 'pati',
            publicName: 'Pati Barber',
            bio: 'Professional barber with more than eight years of experience. Specialist in precision fades, creative designs and beard grooming. Punctuality and style guaranteed.',
            bannerPhotoUrl: 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&q=80&w=800',
            avatarPhotoUrl: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=400',
            isMobile: true,
            isAvailableNow: true,
            serviceArea: 'Brickell & Wynwood area',
            addressLine: null,
            social: [
                'whatsapp_url' => '#',
                'instagram_url' => '#',
                'tiktok_url' => '#',
                'facebook_url' => '#',
            ],
        );
        $this->attachPhotos($pati, [
            'https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&q=80&w=400',
            'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&q=80&w=400',
            'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&q=80&w=400',
        ]);
        $this->createService($pati, $types['vip-haircut-beard'], 'VIP Haircut + Beard', 60, 40, ServiceCategory::Fade, 1);
        $this->createService($pati, $types['haircut'], 'Haircut', 40, 30, ServiceCategory::Classic, 2);

        $miguel = $this->createProvider(
            username: 'miguelb',
            email: 'miguel@glowstudiovip.com',
            phone: '3055550101',
            slug: 'miguel',
            publicName: 'Miguel',
            bio: 'Master barber. Classic and modern cuts.',
            bannerPhotoUrl: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=800',
            avatarPhotoUrl: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=400',
            isMobile: false,
            isAvailableNow: true,
        );
        $this->createService($miguel, $types['vip-haircut-beard'], 'VIP Haircut + Beard', 60, 45, ServiceCategory::Fade, 1);
        $this->createService($miguel, $types['haircut'], 'Haircut', 40, 30, ServiceCategory::Classic, 2);
        $this->createService($miguel, $types['beard-grooming'], 'Beard grooming', 25, 18, ServiceCategory::Beard, 3);

        $andres = $this->createProvider(
            username: 'andresb',
            email: 'andres@glowstudiovip.com',
            phone: '3055550102',
            slug: 'andres',
            publicName: 'Andrés',
            bio: 'Beard and classic shaving specialist.',
            bannerPhotoUrl: 'https://images.unsplash.com/photo-1583864697784-a0efc8379f70?auto=format&fit=crop&q=80&w=800',
            avatarPhotoUrl: 'https://images.unsplash.com/photo-1583864697784-a0efc8379f70?auto=format&fit=crop&q=80&w=400',
            isMobile: false,
            isAvailableNow: false,
        );
        $this->createService($andres, $types['beard-grooming'], 'Beard grooming', 25, 20, ServiceCategory::Beard, 1);
        $this->createService($andres, $types['kids-haircut'], 'Kids haircut', 30, 22, ServiceCategory::Kids, 2);

        $luis = $this->createProvider(
            username: 'luisb',
            email: 'luis@glowstudiovip.com',
            phone: '3055550103',
            slug: 'luis',
            publicName: 'Luis',
            bio: 'Creative designs and kids haircuts.',
            bannerPhotoUrl: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=800',
            avatarPhotoUrl: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=400',
            isMobile: false,
            isAvailableNow: false,
        );
        $this->createService($luis, $types['haircut'], 'Haircut', 45, 32, ServiceCategory::Classic, 1);
        $this->createService($luis, $types['kids-haircut'], 'Kids haircut', 35, 25, ServiceCategory::Kids, 2);

        $daniela = $this->createProvider(
            username: 'danielab',
            email: 'daniela@glowstudiovip.com',
            phone: '3055550104',
            slug: 'daniela',
            publicName: 'Daniela',
            bio: 'Color, styling and treatments.',
            bannerPhotoUrl: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=800',
            avatarPhotoUrl: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=400',
            isMobile: true,
            isAvailableNow: false,
        );
        $this->createService($daniela, $types['vip-haircut-beard'], 'VIP Haircut + Beard', 65, 42, ServiceCategory::Fade, 1);
        $this->createService($daniela, $types['haircut'], 'Haircut', 45, 35, ServiceCategory::Classic, 2);
        $this->createService($daniela, $types['kids-haircut'], 'Kids haircut', 30, 24, ServiceCategory::Kids, 3);
        $this->createService($daniela, $types['hair-coloring'], 'Hair Coloring', 90, 50, ServiceCategory::Color, 4);
        $this->createService($daniela, $types['hot-towel-shave'], 'Hot Towel Shave', 30, 25, ServiceCategory::Beard, 5);
    }

    /**
     * @param  array<string, string>  $social
     */
    private function createProvider(
        string $username,
        string $email,
        string $phone,
        string $slug,
        string $publicName,
        string $bio,
        string $bannerPhotoUrl,
        string $avatarPhotoUrl,
        bool $isMobile,
        bool $isAvailableNow,
        ?string $serviceArea = null,
        ?string $addressLine = null,
        array $social = [],
    ): Provider {
        $user = User::create([
            'name' => $publicName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password',
        ]);

        // Seeded accounts are ready-to-use demo logins, not users
        // mid-registration — leaving this unverified would lock every one
        // of them out behind the email-verification gate. email_verified_at
        // isn't in User's #[Fillable] allowlist, hence markEmailAsVerified()
        // rather than a mass-assigned column.
        $user->markEmailAsVerified();

        return Provider::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'public_name' => $publicName,
            'bio' => $bio,
            'banner_photo_url' => $bannerPhotoUrl,
            'avatar_photo_url' => $avatarPhotoUrl,
            'is_mobile' => $isMobile,
            'is_available_now' => $isAvailableNow,
            'published_at' => now(),
            'service_area' => $serviceArea,
            'address_line' => $addressLine,
            'whatsapp_url' => $social['whatsapp_url'] ?? null,
            'instagram_url' => $social['instagram_url'] ?? null,
            'tiktok_url' => $social['tiktok_url'] ?? null,
            'facebook_url' => $social['facebook_url'] ?? null,
        ]);
    }

    private function createService(
        Provider $provider,
        ServiceType $type,
        string $name,
        int $durationMinutes,
        int $price,
        ServiceCategory $category,
        int $position,
    ): Service {
        return Service::create([
            'provider_id' => $provider->id,
            'service_type_id' => $type->id,
            'name' => $name,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'category' => $category->value,
            'position' => $position,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $urls
     */
    private function attachPhotos(Provider $provider, array $urls): void
    {
        foreach ($urls as $position => $url) {
            ProviderPhoto::create([
                'provider_id' => $provider->id,
                'url' => $url,
                'position' => $position,
            ]);
        }
    }
}
