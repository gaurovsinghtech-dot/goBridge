<?php

namespace App\Http\Controllers;

use App\Models\Locale;
use App\Services\I18n\I18nFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class I18nController extends Controller
{
    public function __construct(
        private I18nFileService $i18nFiles
    ) {}

    /**
     * Return JSON dictionary for the given locale (flat key => value) from resources/js/locales/{locale}.json.
     */
    public function show(Request $request, string $locale): JsonResponse
    {
        $locales = Locale::forSwitcherCached();
        $enabled = array_column($locales, 'code');
        if (empty($enabled)) {
            $enabled = ['en'];
        }
        if (! in_array($locale, $enabled, true)) {
            $locale = Locale::defaultCode();
        }
        if (! in_array($locale, $enabled, true)) {
            $locale = $enabled[0];
        }

        $dictionary = $this->i18nFiles->getFlatDictionary($locale);
        $etag = md5($locale.'_'.count($dictionary));

        if ($request->header('If-None-Match') === '"'.$etag.'"') {
            return response()->json(null, 304, [
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'ETag' => '"'.$etag.'"',
            ]);
        }

        return response()
            ->json(['translation' => $dictionary])
            ->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800')
            ->header('ETag', '"'.$etag.'"');
    }
}
