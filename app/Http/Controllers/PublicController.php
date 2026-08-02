<?php

namespace App\Http\Controllers;

use App\Support\MockData;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Public/Home', [
            'stats' => MockData::homeStats(),
            'services' => MockData::homeServices(),
        ]);
    }

    public function providers(): Response
    {
        return Inertia::render('Public/Providers', [
            'providers' => MockData::providers(),
        ]);
    }

    public function profile(string $provider): Response
    {
        $profile = MockData::providerProfile($provider);

        abort_if($profile === null, 404);

        return Inertia::render('Public/Profile', [
            'provider' => $profile,
        ]);
    }

    public function booking(string $provider, int $service): Response
    {
        $profile = MockData::providerProfile($provider);
        $serviceData = MockData::findService($provider, $service);

        abort_if($profile === null || $serviceData === null, 404);

        return Inertia::render('Public/Booking', [
            'provider' => ['slug' => $profile['slug'], 'name' => $profile['name']],
            'service' => $serviceData,
        ]);
    }

    public function signIn(): Response
    {
        return Inertia::render('Auth/SignIn');
    }

    public function signUp(): Response
    {
        return Inertia::render('Auth/SignUp');
    }
}
