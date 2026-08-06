<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SetInitialPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InitialPasswordController extends Controller
{
    /**
     * Sets the password for the currently authenticated user — but only
     * once. A user who already has one must go through the existing
     * "change password" settings flow (current_password required), not this
     * one: without this guard, anyone already logged in could overwrite
     * their own password here without proving they know the current one.
     */
    public function update(Request $request, SetInitialPassword $setInitialPassword): RedirectResponse
    {
        abort_if($request->user()->password !== null, 403);

        $setInitialPassword->handle($request->user(), $request->all());

        return redirect()->route('admin.perfil');
    }
}
