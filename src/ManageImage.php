<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 9/7/18
 * Time: 3:33 PM
 */

namespace Fynduck\FilesUpload;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ManageImage
{
    public function saveImage($image, $folder, $name, $sizes, $oldImg, $do, $bg, $diskName = 'public')
    {
        /**
         * remove old images
         */
        if ($oldImg)
            (new PrepareFile())->deleteImages($folder, $oldImg, $sizes, $diskName);

        if (is_uploaded_file($image))
            Storage::disk($diskName)->putFileAs($folder, $image, $name);
        else
            Storage::disk($diskName)->put($folder . '/' . $name, $image);

        switch ($do) {
            case 'crop':
                foreach ($sizes as $folderSize => $size) {
                    $this->cropImage($folder, $name, $folderSize, $size, $bg, $diskName);
                }
                break;
            case 'resize':
                foreach ($sizes as $folderSize => $size) {
                    $this->resizeImage($folder, $name, $folderSize, $size, $diskName);
                }
                break;
        }
    }

    /**
     * Crop image
     * @param $folder
     * @param $imageName
     * @param $folderSize
     * @param $size
     * @param $bg
     * @param $diskName
     */
    public function cropImage($folder, $imageName, $folderSize, $size, $bg, $diskName)
    {
        /**
         * Get original image
         */
        $urlImg = Storage::disk($diskName)->get($folder . '/' . $imageName);
        $image = Image::make($urlImg);

        /**
         * Check isset folder size
         */
        (new PrepareFile())->checkFolder($folder . '/' . $folderSize, $diskName);

        $widthImg = $image->width();
        $heightImg = $image->height();

        /**
         * Verify width / height for crop
         */
        if ($size['width'] && $size['height']) {
            if (($widthImg / $size['width']) > ($heightImg / $size['height'])) {
                $image->resize($size['width'], null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            } else {
                $image->resize(null, $size['height'], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            /**
             * Add background if width || height less than new resize
             */
            $background = Image::canvas($size['width'], $size['height'], $bg);
            $image = $background->insert($image, 'center');
        } else {
            $image->resize($size['width'], $size['height'], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        /**
         * Save crop
         */
        $image->save(Storage::disk($diskName)->getDriver()->getAdapter()->getPathPrefix() . $folder . '/' . $folderSize . '/' . $imageName);
    }

    /**
     * Resize image
     * @param $folder
     * @param $imageName
     * @param $folderSize
     * @param $size
     * @param $diskName
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function resizeImage($folder, $imageName, $folderSize, $size, $diskName)
    {
        /**
         * Get original image
         */
        $urlImg = Storage::disk($diskName)->get(str_finish($folder, '/') . $imageName);
        $image = Image::make($urlImg);

        /**
         * Check isset folder size
         */
        (new PrepareFile())->checkFolder($folderSize, $diskName);

        $widthImg = $image->width();
        $heightImg = $image->height();

        /**
         * Verify width / height for resize
         */
        if (($widthImg / $size['width']) > ($heightImg / $size['height']))
            $image->widen($size['width']);
        else
            $image->heighten($size['height']);

        /**
         * Save resize
         */
        $image->save(Storage::disk($diskName)->getDriver()->getAdapter()->getPathPrefix() . $folder . '/' . $folderSize . '/' . $imageName);
    }
}
