<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Requests\Admin\UpdatePublicationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * No {id} in the route — the target is always $request->user() /
     * ->provider, so there is no cross-tenant vector to guard against.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update([
            'username' => $data['username'],
            'phone' => $data['phone'],
        ]);

        $providerChanges = [
            'public_name' => $data['publicName'],
            'business_category' => $data['businessCategory'] ?? null,
            'business_subcategories' => $data['businessSubcategories'] ?? [],
            'bio' => $data['bio'],
            'is_mobile' => $data['isMobile'],
            'service_area' => $data['serviceArea'],
            'address_line' => $data['addressLine'],
        ];

        // Only when the client sent it — see UpdateProfileRequest.
        if (array_key_exists('homeService', $data)) {
            $providerChanges['home_service'] = $data['homeService'];
        }

        $user->provider->update($providerChanges);

        return to_route('admin.perfil')->with('success', 'admin.profileUpdated');
    }

    /**
     * Publishing with zero active services is blocked (422): a published
     * provider with nothing to book is a dead-end listing on /proveedores.
     * Unpublishing always succeeds — it's the exit and must never be blocked.
     */
    public function updatePublication(UpdatePublicationRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $published = $request->boolean('published');

        if ($published && $provider->services()->active()->doesntExist()) {
            throw ValidationException::withMessages([
                'published' => __('admin.publishBlockedNoServices'),
            ]);
        }

        $provider->update(['published_at' => $published ? now() : null]);

        return to_route('admin.perfil')->with(
            'success',
            $published ? 'admin.profilePublished' : 'admin.profileUnpublished',
        );
    }
}
