<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\Tests\TestCase;
use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReplaceImageTest extends TestCase
{
    private const array SIZES = [
        'sm' => ['width' => 40, 'height' => 40],
        'md' => ['width' => 80, 'height' => 80],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_replacing_removes_the_old_original_and_every_old_size(): void
    {
        $oldName = $this->upload('Old title');
        Storage::disk('public')->put('articles/legacy/old_title.jpg', 'size removed from the settings since');

        $newName = UploadFile::file(UploadedFile::fake()->image('new.jpg', 120, 120))
            ->setFolder('articles')
            ->setName('New title')
            ->setSizes(self::SIZES)
            ->setOptimize(false)
            ->setOverwrite($oldName)
            ->save('crop');

        $this->assertSame('new_title.jpg', $newName);
        $disk = Storage::disk('public');
        $disk->assertMissing(['articles/old_title.jpg', 'articles/sm/old_title.jpg', 'articles/md/old_title.jpg', 'articles/legacy/old_title.jpg']);
        $disk->assertExists(['articles/new_title.jpg', 'articles/sm/new_title.jpg', 'articles/md/new_title.jpg']);
    }

    public function test_replacing_with_the_same_title_stores_a_new_name_and_removes_the_old_files(): void
    {
        $oldName = $this->upload('Summer sale');

        $newName = UploadFile::file(UploadedFile::fake()->image('again.jpg', 120, 120))
            ->setFolder('articles')
            ->setName('Summer sale')
            ->setSizes(self::SIZES)
            ->setOptimize(false)
            ->setOverwrite($oldName)
            ->save('crop');

        $this->assertNotSame($oldName, $newName);
        $this->assertStringStartsWith('summer_sale_', $newName);
        $disk = Storage::disk('public');
        $disk->assertMissing(['articles/summer_sale.jpg', 'articles/sm/summer_sale.jpg', 'articles/md/summer_sale.jpg']);
        $disk->assertExists(["articles/{$newName}", "articles/sm/{$newName}", "articles/md/{$newName}"]);
    }

    public function test_replacing_removes_old_sizes_encoded_to_another_format(): void
    {
        $oldName = UploadFile::file(UploadedFile::fake()->image('old.png', 120, 120))
            ->setFolder('articles')
            ->setName('Promo')
            ->setSizes(self::SIZES)
            ->setEncodeFormat('webp')
            ->setOptimize(false)
            ->save('crop');

        $this->assertSame('promo.webp', $oldName);

        $newName = UploadFile::file(UploadedFile::fake()->image('new.jpg', 120, 120))
            ->setFolder('articles')
            ->setName('Promo')
            ->setSizes(self::SIZES)
            ->setOptimize(false)
            ->setOverwrite($oldName)
            ->save('crop');

        $this->assertSame('promo.jpg', $newName);
        $disk = Storage::disk('public');
        $disk->assertMissing(['articles/promo.webp', 'articles/sm/promo.webp', 'articles/md/promo.webp']);
        $disk->assertExists(['articles/promo.jpg', 'articles/sm/promo.jpg', 'articles/md/promo.jpg']);
    }

    public function test_the_old_image_stays_when_the_new_upload_fails(): void
    {
        $oldName = $this->upload('Keep me');

        try {
            UploadFile::file('not a file')
                ->setFolder('articles')
                ->setName('Broken')
                ->setOverwrite($oldName)
                ->save();
            $this->fail('The upload should have been rejected.');
        } catch (\InvalidArgumentException) {
            Storage::disk('public')->assertExists(['articles/keep_me.jpg', 'articles/sm/keep_me.jpg']);
        }
    }

    public function test_a_corrupt_image_keeps_the_old_one_and_leaves_no_partial_files(): void
    {
        $oldName = $this->upload('Keep me');

        try {
            UploadFile::file(UploadedFile::fake()->create('broken.jpg', 5, 'image/jpeg'))
                ->setFolder('articles')
                ->setName('Broken')
                ->setSizes(self::SIZES)
                ->setOptimize(false)
                ->setOverwrite($oldName)
                ->save('crop');
            $this->fail('A corrupt image should not be stored.');
        } catch (\Intervention\Image\Interfaces\ExceptionInterface) {
            Storage::disk('public')->assertExists(['articles/keep_me.jpg', 'articles/sm/keep_me.jpg']);
            Storage::disk('public')->assertMissing(['articles/broken.jpg', 'articles/sm/broken.jpg']);
        }
    }

    public function test_other_images_in_the_folder_are_left_alone(): void
    {
        $oldName = $this->upload('First');
        $this->upload('Second');

        UploadFile::file(UploadedFile::fake()->image('new.jpg', 120, 120))
            ->setFolder('articles')
            ->setName('Third')
            ->setSizes(self::SIZES)
            ->setOptimize(false)
            ->setOverwrite($oldName)
            ->save('crop');

        Storage::disk('public')->assertExists(['articles/second.jpg', 'articles/sm/second.jpg', 'articles/md/second.jpg']);
    }

    private function upload(string $title): string
    {
        return UploadFile::file(UploadedFile::fake()->image('photo.jpg', 120, 120))
            ->setFolder('articles')
            ->setName($title)
            ->setSizes(self::SIZES)
            ->setOptimize(false)
            ->save('crop');
    }
}
