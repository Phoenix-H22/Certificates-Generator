<?php

namespace App\Certificates\Rendering;

/**
 * First-guess font size for the recipient name so the layout is right even
 * before the in-page fit-text script refines it (and if JS never runs).
 */
final class NameSizer
{
    public const MIN_PT = 20;

    public function initialFontPt(string $name): int
    {
        $length = mb_strlen(trim($name));

        return match (true) {
            $length <= 18 => 44,
            $length <= 28 => 38,
            $length <= 40 => 32,
            $length <= 55 => 27,
            default => 23,
        };
    }
}
