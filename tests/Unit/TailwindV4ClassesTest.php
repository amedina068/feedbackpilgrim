<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TailwindV4ClassesTest extends TestCase
{
    /**
     * Tailwind CSS 4 removed the `*-opacity-*` utilities. A class like
     * `bg-opacity-75` makes no CSS, so the color shows at full opacity.
     * Use the slash syntax, for example `bg-black/75`.
     */
    public function test_the_views_and_components_use_no_removed_opacity_utility(): void
    {
        $offenders = [];

        foreach (['resources/js', 'resources/views'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$directory));

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(vue|js|php)$/', $file->getFilename())) {
                    continue;
                }

                if (preg_match_all('/\b(?:bg|text|border|ring|divide|placeholder)-opacity-\d+\b/', file_get_contents($file->getPathname()), $matches)) {
                    $offenders[] = $file->getFilename().': '.implode(', ', array_unique($matches[0]));
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_the_dropdown_panel_uses_the_dark_surface(): void
    {
        $dropdown = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/BaseDropdown.vue');

        $this->assertStringNotContainsString('bg-white ', $dropdown);
        $this->assertStringContainsString('bg-fp-elevated', $dropdown);
    }
}
