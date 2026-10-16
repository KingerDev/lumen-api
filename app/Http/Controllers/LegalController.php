<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public pages App Store Connect links to. Slovak or English by ?lang=,
 * otherwise by the browser's language.
 */
class LegalController extends Controller
{
    public function privacy(Request $request): View
    {
        return $this->page($request, 'privacy');
    }

    public function support(Request $request): View
    {
        return $this->page($request, 'support');
    }

    private function page(Request $request, string $view): View
    {
        $lang = in_array($request->query('lang'), ['sk', 'en'], true)
            ? $request->query('lang')
            : ($request->getPreferredLanguage(['sk', 'en']) ?? 'en');

        return view("legal.{$view}", [
            'lang' => $lang,
            'contact' => config('services.lumen.contact_email'),
        ]);
    }
}
