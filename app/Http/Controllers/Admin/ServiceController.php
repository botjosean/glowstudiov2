<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;

class ServiceController extends Controller
{
    /**
     * Ownership for update/deactivate/activate is enforced by ->can('update', 'service')
     * in routes/web.php, not here.
     */
    public function store(ServiceRequest $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $data = $request->validated();

        $position = ($provider->services()->max('position') ?? 0) + 1;

        Service::create([
            'provider_id' => $provider->id,
            // The sheet has no type picker, only category — new services
            // default to the catalog type their category maps to, so they
            // still count toward the Home page's ServiceType aggregation.
            'service_type_id' => $this->serviceTypeIdFor($data['category']),
            'name' => $data['name'],
            'duration_minutes' => $data['durationMinutes'],
            'price' => $data['price'],
            'category' => $data['category'],
            'position' => $position,
            'is_active' => true,
            'home_available' => $data['homeAvailable'] ?? false,
        ]);

        return to_route('admin.servicios')->with('success', 'admin.serviceCreated');
    }

    /**
     * service_type_id follows the category, but only when the category itself
     * changed. Re-deriving it on every edit would clobber a more specific type
     * an existing service already carries (e.g. a seeded "Hot Towel Shave"
     * service categorized as Beard but typed more specifically) just because
     * the admin corrected its price. Leaving it pinned across a genuine
     * recategorisation is the worse failure though: a service moved to Nails
     * would keep counting toward "Skin Fade" on the public Home page.
     */
    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();

        $changes = [
            'name' => $data['name'],
            'duration_minutes' => $data['durationMinutes'],
            'price' => $data['price'],
            'category' => $data['category'],
        ];

        // Only when the client sent it — a stale pre-deploy bundle that
        // omits the field must not silently switch home service off.
        if (array_key_exists('homeAvailable', $data)) {
            $changes['home_available'] = $data['homeAvailable'];
        }

        if ($service->category->value !== $data['category']) {
            $changes['service_type_id'] = $this->serviceTypeIdFor($data['category']);
        }

        $service->update($changes);

        return to_route('admin.servicios')->with('success', 'admin.serviceUpdated');
    }

    public function deactivate(Service $service): RedirectResponse
    {
        $service->update(['is_active' => false]);

        return to_route('admin.servicios')->with('success', 'admin.serviceDeactivated');
    }

    public function activate(Service $service): RedirectResponse
    {
        $service->update(['is_active' => true]);

        return to_route('admin.servicios')->with('success', 'admin.serviceActivated');
    }

    private function serviceTypeIdFor(string $category): ?int
    {
        $slug = ServiceCategory::from($category)->defaultTypeSlug();

        // Beauty categories map to no catalog type — the service simply stays
        // typeless rather than borrowing an unrelated barbershop one.
        if ($slug === null) {
            return null;
        }

        return ServiceType::where('slug', $slug)->value('id');
    }
}
