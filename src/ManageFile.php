<?php
/**
 * Created by PhpStorm.
 * User: stass
 * Date: 26.09.2018
 * Time: 19:40
 */

namespace Fynduck\FilesUpload;

use Illuminate\Support\Facades\Storage;

class ManageFile
{
    public function saveFile($file, $folder, $name, $oldFile, $diskName = 'public')
    {
        /**
         * remove old images
         */
        if ($oldFile)
            (new PrepareFile())->deleteImages($folder, $name);

        Storage::disk($diskName)->putFileAs($folder, $file, $name);
    }
}
