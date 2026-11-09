<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use App\Services\ShortLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShortLinkController extends Controller
{
    public function __construct(
        protected ShortLinkService $shortLinkService
    ) {}

    public function redirect(Request $request, string $code): RedirectResponse|View
    {
        $shortLink = ShortLink::where('code', $code)->first();

        if (! $shortLink) {
            abort(404, 'Short link not found.');
        }

        if ($shortLink->expires_at && $shortLink->expires_at->isPast()) {
            abort(404, 'Short link has expired.');
        }

        // Show interstitial warning if flagged and user hasn't explicitly proceeded
        if ($shortLink->is_flagged && ! $request->boolean('proceed')) {
            return view('shortlinks.warning', [
                'targetUrl' => $shortLink->target_url,
                'proceedUrl' => route('shortlink.redirect', ['code' => $code, 'proceed' => 1]),
            ]);
        }

        $shortLink->increment('clicks');

        return redirect()->away($shortLink->target_url, 302);
    }
}
