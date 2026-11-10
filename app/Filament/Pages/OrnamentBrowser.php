<?php

namespace App\Filament\Pages;

use App\Services\OrnamentRegistry;
use App\Services\SvgSanitizer;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrnamentBrowser extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Catalog & Design';

    protected static ?string $navigationLabel = 'Ornament Browser';

    protected static ?string $title = 'Ornament Library Browser';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.ornament-browser';

    public string $search = '';

    public string $setFilter = 'all';

    public string $anchorFilter = 'all';

    public string $c1 = '#800020';

    public string $c2 = '#D4AF37';

    public string $c3 = '#374151';

    public string $previewBg = 'light';

    public static function canAccess(): bool
    {
        return auth('superadmin')->check();
    }

    /**
     * Get all available sets with item counts.
     */
    public function getSets(): array
    {
        $all = OrnamentRegistry::all();
        $counts = [];

        foreach ($all as $item) {
            $set = $item['set'] ?? 'other';
            $counts[$set] = ($counts[$set] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Get all available anchors with item counts.
     */
    public function getAnchors(): array
    {
        $all = OrnamentRegistry::all();
        $counts = [];

        foreach ($all as $item) {
            $anchor = $item['anchor'] ?? 'other';
            $counts[$anchor] = ($counts[$anchor] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Set a preset color palette.
     */
    public function applyPreset(string $preset): void
    {
        match ($preset) {
            'royal-gold' => [
                $this->c1 = '#800020', // Crimson Burgundy
                $this->c2 = '#D4AF37', // Metallic Gold
                $this->c3 = '#C5A059', // Antique Gold
            ],
            'emerald-copper' => [
                $this->c1 = '#1E5E41', // Deep Emerald
                $this->c2 = '#B85D19', // Warm Copper
                $this->c3 = '#5C9E7A', // Sage Mint
            ],
            'midnight-rose' => [
                $this->c1 = '#0F172A', // Midnight Navy
                $this->c2 = '#E11D48', // Vibrant Rose
                $this->c3 = '#94A3B8', // Slate Grey
            ],
            'classic-monochrome' => [
                $this->c1 = '#111827', // Pitch Ink
                $this->c2 = '#6B7280', // Medium Grey
                $this->c3 = '#9CA3AF', // Light Grey
            ],
            default => null,
        };
    }

    /**
     * Get filtered ornaments list with tinted SVGs.
     */
    public function getOrnaments(): array
    {
        $all = OrnamentRegistry::all();
        $filtered = [];

        $searchQuery = strtolower(trim($this->search));

        foreach ($all as $item) {
            // Filter by set
            if ($this->setFilter !== 'all' && ($item['set'] ?? '') !== $this->setFilter) {
                continue;
            }

            // Filter by anchor
            if ($this->anchorFilter !== 'all' && ($item['anchor'] ?? '') !== $this->anchorFilter) {
                continue;
            }

            // Search query filter
            if ($searchQuery !== '') {
                $id = strtolower($item['id'] ?? '');
                $nameEn = strtolower($item['name_en'] ?? '');
                $nameAm = strtolower($item['name_am'] ?? '');
                $set = strtolower($item['set'] ?? '');
                $tags = array_map('strtolower', $item['tags'] ?? []);

                $matches = str_contains($id, $searchQuery)
                    || str_contains($nameEn, $searchQuery)
                    || str_contains($nameAm, $searchQuery)
                    || str_contains($set, $searchQuery)
                    || in_array($searchQuery, $tags, true);

                if (! $matches) {
                    $tagMatch = false;
                    foreach ($tags as $tag) {
                        if (str_contains($tag, $searchQuery)) {
                            $tagMatch = true;
                            break;
                        }
                    }
                    if (! $tagMatch) {
                        continue;
                    }
                }
            }

            // Load and tint SVG
            $rawSvg = OrnamentRegistry::loadRawSvg($item['id']);
            $tintedSvg = null;

            if ($rawSvg) {
                $sanitized = SvgSanitizer::sanitize($rawSvg);
                $tintedSvg = str_replace(
                    ['{{c1}}', '{{c2}}', '{{c3}}'],
                    [$this->c1, $this->c2, $this->c3],
                    $sanitized
                );
            }

            $item['tinted_svg'] = $tintedSvg;
            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Download an ornament SVG file (tinted or original).
     */
    public function downloadSvg(string $id, bool $tinted = true): StreamedResponse
    {
        $item = OrnamentRegistry::find($id);

        if (! $item) {
            abort(404, 'Ornament not found');
        }

        $rawSvg = OrnamentRegistry::loadRawSvg($id);

        if (! $rawSvg) {
            abort(404, 'SVG file not found');
        }

        if ($tinted) {
            $content = str_replace(
                ['{{c1}}', '{{c2}}', '{{c3}}'],
                [$this->c1, $this->c2, $this->c3],
                $rawSvg
            );
            $filename = "{$id}-tinted.svg";
        } else {
            $content = $rawSvg;
            $filename = "{$id}.svg";
        }

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
