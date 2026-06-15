<?php

namespace Fynduck\FilesUpload\Tests\Unit;

use Fynduck\FilesUpload\Data\ManipulationOptions;
use Fynduck\FilesUpload\Tests\TestCase;

class ManipulationOptionsTest extends TestCase
{
    public function test_it_clamps_blur(): void
    {
        $this->assertNull(ManipulationOptions::normalizeBlur(-1));
        $this->assertSame(0, ManipulationOptions::normalizeBlur(0));
        $this->assertSame(100, ManipulationOptions::normalizeBlur(250));
    }

    public function test_it_rejects_out_of_range_brightness(): void
    {
        $this->assertSame(50, ManipulationOptions::normalizeBrightness(50));
        $this->assertNull(ManipulationOptions::normalizeBrightness(150));
        $this->assertNull(ManipulationOptions::normalizeBrightness(-150));
    }

    public function test_it_clamps_quality_with_default_fallback(): void
    {
        $this->assertSame(90, ManipulationOptions::normalizeQuality(-5));
        $this->assertSame(90, ManipulationOptions::normalizeQuality(null));
        $this->assertSame(100, ManipulationOptions::normalizeQuality(250));
        $this->assertSame(40, ManipulationOptions::normalizeQuality(40));
    }

    public function test_it_round_trips_through_array_with_normalisation(): void
    {
        $options = ManipulationOptions::fromArray([
            'disk'       => 'public',
            'folder'     => 'Post',
            'name'       => 'image',
            'blur'       => 250,
            'brightness' => 999,
            'encode'     => 'WEBP',
            'quality'    => 250,
        ]);

        $this->assertSame(100, $options->blur);
        $this->assertNull($options->brightness);
        $this->assertSame('webp', $options->encode);
        $this->assertSame(100, $options->quality);

        $this->assertEquals($options, ManipulationOptions::fromArray($options->toArray()));
    }
}
