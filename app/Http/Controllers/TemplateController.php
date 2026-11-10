<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Template;
use App\Services\DesignValidator;
use App\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Display template gallery with categories, kind toggle, search, and upload support.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $kind = in_array($request->input('kind'), ['invitation', 'thank_you'], true)
            ? $request->input('kind')
            : 'invitation';

        $selectedCategory = $request->input('category');
        $search = $request->input('search');

        $categories = \Illuminate\Support\Facades\Cache::remember("template_gallery_categories_{$kind}", 1800, function () use ($kind) {
            $categoryCounts = Template::query()
                ->where('is_active', true)
                ->where('kind', $kind)
                ->selectRaw('category_id, count(*) as count')
                ->groupBy('category_id')
                ->pluck('count', 'category_id');

            return Category::orderBy('sort')->get()->map(function ($cat) use ($categoryCounts) {
                $cat->templates_count = $categoryCounts[$cat->id] ?? 0;
                return $cat;
            });
        });

        $templatesQuery = Template::query()
            ->where('is_active', true)
            ->where('kind', $kind)
            ->where(function ($q) use ($user) {
                $q->where('is_system', true);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->with('category');

        if ($selectedCategory && $selectedCategory !== 'all') {
            $templatesQuery->whereHas('category', function ($q) use ($selectedCategory) {
                $q->where('slug', $selectedCategory);
            });
        }

        if ($search) {
            $templatesQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('name_en', 'like', "%{$search}%")
                            ->orWhere('name_am', 'like', "%{$search}%");
                    });
            });
        }

        $templates = $templatesQuery->orderBy('sort')->get();

        $canUpload = $user ? $this->entitlementService->can($user, 'upload_templates') : false;

        return Inertia::render('Templates/Index', [
            'categories' => $categories,
            'templates' => $templates,
            'filters' => [
                'kind' => $kind,
                'category' => $selectedCategory ?? 'all',
                'search' => $search ?? '',
            ],
            'canUpload' => $canUpload,
        ]);
    }

    /**
     * Upload custom template background image (gated by upload_templates tier feature).
     */
    public function upload(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $this->entitlementService->can($user, 'upload_templates')) {
            return redirect()->route('plan.index')->with(
                'warning',
                __('Uploading custom template designs requires a paid plan upgrade. Please upgrade your tier.')
            );
        }

        $request->validate([
            'image' => 'required|file|max:8192', // 8 MB max
            'name' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $file = $request->file('image');

        // Security check: real MIME type validation
        $mime = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'image' => __('Only real PNG, JPEG, or WebP images are allowed. SVG and executable files are prohibited.'),
            ]);
        }

        // Security check: Inspect raw bytes for malicious script tags or php blocks
        $rawContent = file_get_contents($file->getRealPath());
        if (preg_match('/<\?php|<\?=|class=|function\(|<script|javascript:/i', $rawContent)) {
            throw ValidationException::withMessages([
                'image' => __('The uploaded file contains prohibited script content.'),
            ]);
        }

        // EXIF Stripping and max 2400px resize using PHP GD
        $originalImage = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => null,
        };

        if (! $originalImage) {
            throw ValidationException::withMessages([
                'image' => __('Corrupted or invalid image file.'),
            ]);
        }

        $origWidth = imagesx($originalImage);
        $origHeight = imagesy($originalImage);

        $maxDimension = 2400;
        $targetWidth = $origWidth;
        $targetHeight = $origHeight;

        if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
            if ($origWidth > $origHeight) {
                $targetWidth = $maxDimension;
                $targetHeight = (int) round(($origHeight / $origWidth) * $maxDimension);
            } else {
                $targetHeight = $maxDimension;
                $targetWidth = (int) round(($origWidth / $origHeight) * $maxDimension);
            }
        }

        $processedImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($processedImage, false);
            imagesavealpha($processedImage, true);
        }

        imagecopyresampled(
            $processedImage,
            $originalImage,
            0, 0, 0, 0,
            $targetWidth,
            $targetHeight,
            $origWidth,
            $origHeight
        );

        $uploadDir = storage_path('app/public/templates/user_uploads');
        if (! file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'user_' . $user->id . '_' . Str::random(16) . '.webp';
        $destPath = $uploadDir . '/' . $filename;

        // Save as clean WebP (dropping all EXIF tags)
        imagewebp($processedImage, $destPath, 90);

        imagedestroy($originalImage);
        imagedestroy($processedImage);

        $storedPath = "/storage/templates/user_uploads/{$filename}";

        $design = [
            'version' => 1,
            'width' => 1080,
            'height' => 1528,
            'background' => [
                'type' => 'image',
                'value' => $storedPath,
            ],
            'layers' => [],
        ];

        DesignValidator::validate($design);

        $category = $request->filled('category_id')
            ? Category::find($request->category_id)
            : (Category::where('slug', 'other')->first() ?? Category::first());

        $template = Template::create([
            'category_id' => $category?->id ?? 1,
            'kind' => 'invitation',
            'name' => $request->input('name') ?: __('Custom Template :time', ['time' => now()->format('M d, H:i')]),
            'preview_path' => $storedPath,
            'design_json' => $design,
            'width' => 1080,
            'height' => 1528,
            'is_system' => false,
            'user_id' => $user->id,
            'is_active' => true,
            'sort' => 0,
        ]);

        return redirect()->route('editor.show', ['template' => $template->id])
            ->with('success', __('Custom template uploaded and loaded into editor!'));
    }
}
