<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Guards the single-resolver rule: client_id is resolved only through ClientContext.
 * No controller or middleware may read currentAccessToken()->client_id directly.
 */
class ClientResolutionTest extends TestCase
{
    public function test_legacy_client_id_helper_is_removed(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/V1/Public/PublicApiController.php'));

        $this->assertStringNotContainsString('function clientId(', $source);
    }

    public function test_no_app_code_reads_token_client_id_outside_client_context(): void
    {
        $files = $this->phpFilesIn(base_path('app'));
        $offenders = [];

        foreach ($files as $file) {
            if (str_ends_with($file, 'Support/ClientContext.php')) {
                continue;
            }

            if (str_contains(file_get_contents($file), 'currentAccessToken()->client_id')) {
                $offenders[] = $file;
            }
        }

        $this->assertSame([], $offenders, 'Resolve client_id via ClientContext, not the raw token.');
    }

    public function test_no_app_code_reads_the_admin_client_id_from_request_input(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(dirname(__DIR__, 3).'/app') as $file) {
            $source = file_get_contents($file);

            if (preg_match('/->fp_client_id\b|(?<!attributes->)(?:get|input|query|post)\(\s*[\'"]fp_client_id[\'"]/', $source)) {
                $offenders[] = $file;
            }
        }

        $this->assertSame([], $offenders, 'Read the admin client id from $request->attributes, not from request input.');
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $dir): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
