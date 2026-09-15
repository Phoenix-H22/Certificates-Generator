<?php

namespace App\Certificates\Rendering;

/**
 * Resolve a public/ relative path for the current render mode.
 *
 * Chromium renders from a string of HTML with no base URL, so fonts and
 * static ornaments must be absolute file:// URLs; the in-browser preview
 * uses ordinary asset() URLs instead.
 */
final class AssetUrl
{
    public function __invoke(string $publicRelativePath, RenderMode $mode): string
    {
        $publicRelativePath = ltrim($publicRelativePath, '/');

        if (! $mode->usesLocalFiles()) {
            return asset($publicRelativePath);
        }

        $absolute = public_path($publicRelativePath);

        return self::fileUrl($absolute);
    }

    /** Build a file:// URL that Chromium accepts on both Windows and POSIX. */
    public static function fileUrl(string $absolutePath): string
    {
        $normalised = str_replace('\\', '/', $absolutePath);

        // Windows drive letters need a third slash: file:///D:/path
        if (! str_starts_with($normalised, '/')) {
            $normalised = '/'.$normalised;
        }

        $segments = array_map('rawurlencode', explode('/', $normalised));

        // Keep the drive colon (D:) intact.
        return 'file://'.str_replace('%3A', ':', implode('/', $segments));
    }
}
