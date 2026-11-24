<?php

namespace Tests\Unit\Support;

use App\Support\ContactPresenter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MetadataCapsTest extends TestCase
{
    public function test_within_bounds_metadata_passes(): void
    {
        ContactPresenter::guardMetadata([
            'plan' => 'enterprise',
            'nested' => ['a' => ['b' => 'c']],
        ]);

        $this->assertTrue(true);
    }

    public function test_reserved_pilgrim_keys_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ContactPresenter::guardMetadata(['pilgrim_last_login' => 'x']);
    }

    public function test_more_than_fifty_keys_is_rejected(): void
    {
        $meta = [];
        for ($i = 0; $i < 51; $i++) {
            $meta['key_'.$i] = $i;
        }

        $this->expectException(ValidationException::class);

        ContactPresenter::guardMetadata($meta);
    }

    public function test_depth_beyond_three_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ContactPresenter::guardMetadata(['a' => ['b' => ['c' => ['d' => 'too deep']]]]);
    }

    public function test_payload_larger_than_16kb_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ContactPresenter::guardMetadata(['big' => str_repeat('x', 17000)]);
    }
}
