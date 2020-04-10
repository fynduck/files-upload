<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 9/7/18
 * Time: 3:51 PM
 */

namespace Fynduck\FilesUpload;

use Illuminate\Support\Facades\Storage;

class PrepareFile
{
    /**
     * Upload image
     * @param $folder
     * @param $typeFile
     * @param $file
     * @param $old_file
     * @param $title
     * @param array $imageSizes
     * @param null $ext
     * @param string $do
     * @param null $bg
     * @param string $diskName
     * @return mixed|string
     */
    public static function uploadFile($folder, $typeFile, $file, $old_file, $title, $imageSizes = [], $ext = null, $do = 'crop', $bg = null, $diskName = 'public')
    {
        $fileName = '';
        if ($old_file)
            $fileName = $old_file;

        if ($file) {
            $folder = trim($folder, '/');
            $fileName = self::generateNameFile($file, $folder, $title, $ext, $diskName);
            self::checkFolder($folder, $diskName);

            if (($ext && $ext == 'svg') || (!$ext && $file->getClientOriginalExtension() == 'svg'))
                $do = null;

            switch ($typeFile) {
                case 'image':
                    (new ManageImage())->saveImage($file, $folder, $fileName, $imageSizes, $old_file, $do, $bg, $diskName);
                    break;
                case 'file':
                    (new ManageFile())->saveFile($file, $folder, $fileName, $old_file, $diskName);
                    break;
            }
        }

        return $fileName;
    }

    /**
     * Generate file name for save
     * @param $file
     * @param $folder
     * @param string $title
     * @param null $extension
     * @param string $diskName
     * @return string
     */
    private static function generateNameFile($file, $folder, $title = null, $extension = null, $diskName = 'public')
    {
        $fileName = \Str::slug($title, '_');

        /**
         * extract extension if not defined
         */
        if (is_null($extension))
            $extension = $file->getClientOriginalExtension();

        /**
         * generate name to file if not defined
         */
        if (is_null($title)) {
            $origName = '.' . $file->getClientOriginalName();

            /**
             * remove extension
             */
            $arrayName = explode('.', $origName);
            array_pop($arrayName);

            $fileName = \Str::slug(implode('_', $arrayName), '_');
        }

        /**
         * replace prohibited symbols
         */
        $pattern = array('/\s+/', '/,/');
        $replace = array('_', '_');
        $fileName = preg_replace($pattern, $replace, $fileName);

        /**
         * Verify if exist file of this name, if exist change file name
         */
        if (Storage::disk($diskName)->exists($folder . '/' . $fileName . '.' . $extension))
            $fileName = $fileName . '_' . \Str::random(8);

        return $fileName . '.' . $extension;
    }

    /**
     * Check isset path if not create
     * @param $folder
     * @param string $diskName
     * @return string
     */
    public static function checkFolder($folder, $diskName = 'public')
    {
        if (!Storage::disk($diskName)->exists($folder))
            Storage::disk($diskName)->makeDirectory($folder);
    }

    /**
     * Delete original image and sizes folders
     * @param $folder
     * @param $sizes
     * @param $name
     * @param $diskName
     * @return bool
     */
    public static function deleteImages($folder, $name, $sizes = [], $diskName = 'public')
    {
        /**
         * remove sizes images
         */
        if ($sizes) {
            foreach ($sizes as $folderSize => $size)
                Storage::disk($diskName)->delete($folder . '/' . $folderSize . '/' . $name);
        }
        /**
         * remove original image
         */
        Storage::disk($diskName)->delete($folder . '/' . $name);

        return true;
    }

    /**
     * Crop images if exist in folder
     * @param $folder
     * @param $imageName
     * @param $imageSizes
     * @param null $bg
     * @param string $diskName
     * @return array
     */
    public static function reCropImages($folder, $imageName, $imageSizes, $bg = null, $diskName = 'public')
    {
        $result = ['main' => '', 'gallery' => ''];
        $folder = trim($folder, '/');

        if (Storage::disk($diskName)->exists($folder)) {
            //images in folder
            $files = [];
            $filesInFolder = Storage::disk($diskName)->allFiles($folder);

            if ($filesInFolder) {
                foreach ($filesInFolder as $item) {
                    $pathinfo = pathinfo($item);
                    if ($pathinfo['dirname'] == $folder)
                        $files[] = $pathinfo['basename'];
                }
                sort($files);
            }

            //resize images
            if ($files) {
                foreach ($files as $file) {
                    foreach ($imageSizes as $folderSize => $size) {
                        (new ManageImage())->cropImage($folder, $file, $folderSize, $size, $bg, 'public');
                    }
                }
                if ($imageName && in_array($imageName, $files)) {
                    $result['main'] = $imageName;
                } else {
                    $result['main'] = $files[0];
                }
                $gallery = array_diff($files, [$result['main']]);
                if ($gallery)
                    $result['gallery'] = $gallery;
            }
        }

        return $result;
    }

    /**
     * Upload base64 file
     * @param $folder
     * @param $typeFile
     * @param $file
     * @param $title
     * @param null $old_file
     * @param array $size
     * @param string $do
     * @param null $bg
     * @param string $diskName `
     * @return mixed|string
     */
    public static function uploadBase64($folder, $typeFile, $file, $title, $old_file = null, $size = [], $do = 'crop', $bg = null, $diskName = 'public')
    {
        list($type, $file) = explode(';', $file);
        list(, $file) = explode(',', $file);
        $format = explode('/', $type)[1];

        return self::uploadFile($folder, $typeFile, base64_decode($file), $old_file, $title, $size, $format, $do, $bg, $diskName);
    }
}
