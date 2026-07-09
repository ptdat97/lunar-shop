<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must NEVER touch the real public/media store: the lunar_testing
        // DB restarts media ids at 1, so media writes/deletes would land in —
        // and clobber — the dev store's per-id folders (this already destroyed
        // seeded demo originals once). fake() reroutes the disk to a throwaway
        // root wiped per test; the '/media' public URL is kept so conversion-URL
        // assertions and the media.conversion route still see real paths.
        Storage::fake('media', ['url' => '/media']);
    }
}
