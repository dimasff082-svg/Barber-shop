<?php

namespace App\Http\Controllers;

use App\Services\PublicCatalog;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    public function home(PublicCatalog $catalog): Response
    {
        return Inertia::render('welcome', [...$catalog->data(), ...$catalog->gallery()]);
    }

    public function services(PublicCatalog $catalog): Response
    {
        return Inertia::render('public/services', $catalog->data());
    }

    public function barbers(PublicCatalog $catalog): Response
    {
        return Inertia::render('public/barbers', $catalog->data());
    }

    public function gallery(PublicCatalog $catalog): Response
    {
        return Inertia::render('public/gallery', [...$catalog->data(), ...$catalog->gallery()]);
    }

    public function contact(PublicCatalog $catalog): Response
    {
        return Inertia::render('public/contact', $catalog->data());
    }
}
