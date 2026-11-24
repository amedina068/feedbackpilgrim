<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The JS tests hold the Vue template guard (tests/js/server-template.test.mjs).
 * The guard protects the server HTML in #app from Vue text interpolation. CI
 * must run it on each pull request.
 */
class ContinuousIntegrationTest extends TestCase
{
    public function test_ci_runs_the_js_tests(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__, 3).'/.github/workflows/ci.yml');

        $this->assertMatchesRegularExpression('/^\s*run:\s*npm run test:js\s*$/m', $workflow);
    }
}
