<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MockData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function citas(): Response
    {
        return Inertia::render('Admin/Citas', [
            'providerName' => 'Pati',
            'appointments' => MockData::appointments(),
        ]);
    }

    public function servicios(): Response
    {
        return Inertia::render('Admin/Servicios', [
            'providerName' => 'Pati',
            'services' => MockData::adminServices(),
        ]);
    }

    public function horario(): Response
    {
        return Inertia::render('Admin/Horario', [
            'providerName' => 'Pati',
            'schedule' => MockData::schedule(),
        ]);
    }

    public function perfil(): Response
    {
        return Inertia::render('Admin/Perfil', [
            'profile' => MockData::adminProfile(),
        ]);
    }

    public function ajustes(): Response
    {
        return Inertia::render('Admin/Ajustes', [
            'providerName' => 'Pati',
        ]);
    }
}
