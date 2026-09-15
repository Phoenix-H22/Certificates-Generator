<?php

namespace App\Certificates\Rendering;

enum RenderMode: string
{
    /** Final PDF via headless Chromium; assets referenced by file:// path. */
    case Pdf = 'pdf';

    /** Same HTML served to a browser for fast iteration; assets via asset(). */
    case Preview = 'preview';

    /** PNG screenshot for template galleries; assets by file:// path. */
    case Thumbnail = 'thumbnail';

    public function usesLocalFiles(): bool
    {
        return $this !== self::Preview;
    }
}
