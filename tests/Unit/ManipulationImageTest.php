<?php

namespace Fynduck\FilesUpload\Tests\Unit;

use Fynduck\FilesUpload\ManipulationImage;
use Fynduck\FilesUpload\Tests\TestCase;

class ManipulationImageTest extends TestCase
{
    public function test_it_supports_legacy_set_extension_alias(): void
    {
        $manipulation = $this->getMockBuilder(ManipulationImage::class)
            ->setConstructorArgs(['images/source.png'])
            ->onlyMethods(['isSupport'])
            ->getMock();
        $manipulation->method('isSupport')->willReturn(true);

        $manipulation->setExtension('webp');

        $reflection = new \ReflectionClass($manipulation);
        $property = $reflection->getProperty('encode');
        $property->setAccessible(true);

        $this->assertSame('webp', $property->getValue($manipulation));
    }

    public function test_it_rejects_unknown_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action does not exist.');

        ManipulationImage::load('images/source.png')->save('unknown');
    }

    public function test_it_requires_sizes_to_process_image(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sizes is required.');

        ManipulationImage::load('images/source.png')
            ->setName('image')
            ->save();
    }

    public function test_it_requires_filename_to_process_image(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filename is required.');

        ManipulationImage::load('images/source.png')
            ->setSizes(['thumb' => ['width' => 100, 'height' => 100]])
            ->save();
    }
}
