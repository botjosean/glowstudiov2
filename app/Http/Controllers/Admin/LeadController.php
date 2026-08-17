<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What the professional does with a WhatsApp request once she has read it.
 *
 * Ownership is enforced by ->can('update', 'lead') in routes/web.php.
 */
class LeadController extends Controller
{
    /**
     * Marks a request as dealt with, or as not a client at all.
     *
     * Converting it into an appointment closes it on its own (see
     * CloseLeadOnAppointment), so this is for the other endings: she answered
     * by WhatsApp and that was that, or it was a supplier, or spam.
     */
    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Lead::STATUS_HANDLED, Lead::STATUS_DISMISSED, Lead::STATUS_NEW])],
        ]);

        $lead->update([
            'status' => $validated['status'],
            // Reopening clears the stamp, so the "waiting too long" alert
            // starts counting again rather than treating it as answered.
            'answered_at' => $validated['status'] === Lead::STATUS_NEW ? null : ($lead->answered_at ?? now()),
        ]);

        return to_route('admin.citas')->with('success', 'admin.leadUpdated');
    }
}
