<?php

namespace App\Http\Controllers;

use App\Services\ShortLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShortLinkController extends Controller
{
    public function __construct(
        protected ShortLinkService $shortLinkService
    ) {}

    public function redirect(Request $request, string $code): RedirectResponse
    {
        $shortLink = $this->shortLinkService->resolve($code);

        if (! $shortLink) {
            abort(404, 'Short link not found or expired.');
        }

        return redirect()->away($shortLink->target_url, 302);
    }
}
