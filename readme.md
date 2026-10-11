# FilesUpload

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)
![Tests](https://github.com/fynduck/files-upload/actions/workflows/FileUpload.yml/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)

| **Laravel** | **files-upload** | **Php version** |
|-------------|------------------|-----------------|
| ``<`` 5.7   | ``^``1.8         | ``>=``5.6       |
| ``>=`` 5.7  | ``^``2.1         | ``>=``5.6       |
| 5.7 - 11.0  | ``^``3.1         | ``>=``7.1       |
| 9.0 - 13.0  | ``^``4.0         | ``>=``8.1       |
| 10.0 - 13.0 | ``^``5.0         | ``>=``8.3       |

Version 5 is built on [Intervention Image v4](https://image.intervention.io/v4). See
[Upgrading from 4.x](#upgrading-from-4x).

## Usage

**Upload file or image**

```php
use Fynduck\FilesUpload\UploadFile;

UploadFile::file($request->file('file')) //or $request->get('base64'), required
    ->setDisk('storage') //default is public
    ->setFolder('Post') //optional
    ->setName('image_name') //optional, default use file name or random in case base64
    ->setOverwrite('old_name.jpg') //optional, the image this upload replaces (see below)
    ->setSizes(['xs' => ['width' => 100, 'height' => 100]]) //(optional) if need other sizes
    ->setFocalPoint(0.3, 0.4) //optional, crop sizes around this point (0-1 of width/height)
    ->setUpscale(false) //optional, never enlarge past the source (default true)
    ->setBackground('#000000') //optional
    ->setBlur(0) //optional, use values between 0 and 100
    ->setBrightness(0) //optional, use values between -100 and +100. brightness 0 for no change
    ->setGreyscale(true) //optional true or false default is false
    ->setOptimize(true) //optional
    ->setEncodeFormat('webp') //optional, re-encode the original and every size: jpg, png, gif, webp, avif
    ->setEncodeQuality() //optional, use values between 0 and 100
    ->queue() //optional, generate sizes in the background (see below)
    ->save('resize'); //save option resize, crop default is resize
```

Raster images (jpg, png, gif, webp, avif, bmp) get sizes and can be re-encoded. Any
other file (SVG, video, PDF…) is stored as uploaded and its sizes are skipped.

**Encode format**

Without `setEncodeFormat()` the original keeps the uploaded format and the sizes use the
same format. With it, the original and every size are re-encoded to that format, so they
always share one extension (`photo.webp`, `xs/photo.webp`).

**Replacing an image**

Pass the name of the image being replaced, e.g. when an article's image is edited:

```php
$article->image = UploadFile::file($request->file('image'))
    ->setFolder('articles')
    ->setName($article->title)
    ->setSizes($sizes)
    ->setOverwrite($article->image)
    ->save('crop');
```

The new file and its sizes are stored first. Only then are the old original and the
old file in every size folder deleted, whatever extension it was encoded to and
including size folders no longer in `$sizes`. Other images in the folder are not touched.
A failed upload leaves the old image in place. The new file never reuses a name that is
still on disk (it gets a `_random` suffix), so browsers and CDNs see a new URL.

**Focal point**

`crop` sizes are cut to their exact width/height ratio. By default the crop is anchored
by the size's `position` (`center`, `top-left`, …). With `setFocalPoint($x, $y)` every
size is cropped around that point instead, so one image can serve different ratios
(e.g. 3:2 cards and a 2:1 banner) without cutting off its subject.

**Background (queued) resizing**

When you request many sizes, generating them inline makes the client wait for every
resize + optimize to finish. Call `->queue()` to store the original immediately and
generate the size variants on your queue workers instead:

```php
UploadFile::file($request->file('file'))
    ->setFolder('Post')
    ->setName('image_name')
    ->setSizes(['xs' => ['width' => 100, 'height' => 100], 'md' => ['width' => 400, 'height' => 400]])
    ->queue() // or ->queue('redis', 'images') ; ->queue(perSize: false) for one job for all sizes
    ->save(); // returns the original filename right away
```

By default each size is dispatched as its own job inside a batch, so sizes are rendered
in parallel across workers. When the batch finishes, a `Fynduck\FilesUpload\Events\ImageSizesGenerated`
event is fired (disk, folder, name, size keys, source path) so you can react (e.g. mark a
record as ready). Make sure a worker is running: `php artisan queue:work`.

Publish the config to change defaults (default disk, quality, and whether background mode
is always on):

```bash
php artisan vendor:publish --tag=files-upload-config
```

```php
// config/files-upload.php
'queue' => [
    'enabled'    => false, // set true to always resize in the background
    'connection' => null,
    'queue'      => null,
    'per_size'   => true,  // one job per size (parallel) vs a single job for all sizes
],
```

**Make new sizes from image**

```php
use Fynduck\FilesUpload\ManipulationImage;

ManipulationImage::load($pathImage)
            ->setDisk('storage') //default is public
            ->setFolder('Post')
            ->setSizes(['xs' => ['width' => 100, 'height' => 100]])
            ->setName('image_name') //name without extension
            ->setOverwrite('old_name.jpg') //optional, remove this name from each size folder first
            ->setFocalPoint(0.5, 0.3) //optional
            ->setBackground('#000000') //optional
            ->setBlur(0) //optional, use values between 0 and 100
            ->setBrightness(0) //optional, use values between -100 and +100. brightness 0 for no change
            ->setGreyscale(true) //optional true or false default is true
            ->setOptimize(true) //optional
            ->setEncodeFormat('webp') //optional, encode every size: jpg, png, gif, webp, avif
            ->setEncodeQuality() //optional, use values between 0 and 100
            ->save('resize'); //save option resize or crop, default is resize
```

**Optimize exist image**

```php
use Fynduck\FilesUpload\ManipulationImage;

ManipulationImage::load('image_name.jpg')
            ->setOptimize(true)
            ->optimize('path_to_image');
```


> **resize**: Resize the image by the maximum width or height
> 
> **crop**: Cut out by size part of the current image with given width and height

## Upgrading from 4.x

- PHP 8.3+ and Laravel 10+ are required; Intervention Image v4 replaces v3. Its Laravel
  config is now `config/intervention-image.php` (`IMAGE_DRIVER` env); a published 4.x
  `config/image.php` keeps working.
- `spatie/laravel-image-optimizer` and `intervention/image-driver-vips` are no longer
  required. Install the optimizer to keep `setOptimize(true)` working; without it,
  optimizing is skipped.
- `setEncodeFormat()` now really re-encodes the original. In 4.x the original kept its
  uploaded bytes under the new extension. Without an encode format the uploaded format
  is kept (4.x set it from the client extension).
- `setOverwrite()` deletes the old image after the new one is stored, in every size
  folder and extension, and the new file gets a fresh name when the old name is still
  on disk. In 4.x it deleted only the original and the configured sizes before saving,
  and reused the name.
- Files that are not raster images are stored as uploaded instead of failing with
  "Unsupported file format".

## For laravel < 5.7 use version 1.8

## Previous stable versions

* [Version ^4.0](https://github.com/fynduck/files-upload/tree/4.2.0)
* [Version ^3.1](https://github.com/fynduck/files-upload/tree/3.1.7)
* [Version ^2.1](https://github.com/fynduck/files-upload/tree/2.1.3)
* [Version ^1.8](https://github.com/fynduck/files-upload/tree/1.8.6.2)

## Installation

You can install the package via composer:

```bash
composer require fynduck/files-upload
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

<a href="https://www.jetbrains.com/?from=files-upload">
<img src="/phpstorm.png" alt="JetBrains" width="50"/>
</a>

## License

The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.
