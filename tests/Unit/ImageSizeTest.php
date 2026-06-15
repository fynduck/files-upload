<?php

namespace Fynduck\FilesUpload\Tests\Unit;

use Fynduck\FilesUpload\Data\ImageSize;
use Fynduck\FilesUpload\Tests\TestCase;

class ImageSizeTest extends TestCase
{
    public function test_it_normalizes_a_partial_array(): void
    {
        $size = ImageSize::fromArray(['width' => '120']);

        $this->assertSame(120, $size->width);
        $this->assertNull($size->height);
        $this->assertSame('center', $size->position);
        $this->assertNull($size->action);
    }

    public function test_it_round_trips_through_to_array(): void
    {
        $size = ImageSize::make(100, 200, 'top', 'crop');

        $this->assertSame(
            ['width' => 100, 'height' => 200, 'position' => 'top', 'action' => 'crop'],
            $size->toArray(),
        );
        $this->assertEquals($size, ImageSize::fromArray($size->toArray()));
    }

    public function test_it_reports_when_dimensions_are_missing(): void
    {
        $this->assertTrue(ImageSize::make(100)->hasDimensions());
        $this->assertTrue(ImageSize::make(null, 100)->hasDimensions());
        $this->assertFalse(ImageSize::make()->hasDimensions());
    }
}
