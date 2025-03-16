# FilesUpload

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)
[![Total Downloads](https://img.shields.io/packagist/dt/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)

| **Laravel** | **files-upload** | **Php version** |
|-------------|------------------|-----------------|
| ``<`` 5.7   | ``^``1.8         | ``>=``5.6       |
| ``>=`` 5.7  | ``^``2.1         | ``>=``5.6       |
| 5.7 - 11.0  | ``^``3.1         | ``>=``7.1       |
| ``>=`` 9.0  | ``^``4.0         | ``>=``8.0       |

## Usage

**Upload file or image**

```php
use Fynduck\FilesUpload\UploadFile;

UploadFile::file($request->file('file')) //or $request->get('base64'), required
    ->setDisk('storage') //default is public
    ->setFolder('Post') //optional
    ->setName('image_name') //optional, default use file name or random in case base64
    ->setOverwrite('old_name.jpg') //optional, remove file with old name
    ->setSizes(['xs' => ['width' => 100, 'height' => 100]]) //(optional) if need other sizes
    ->setExtension('png') //(optional) default use file extension
    ->setBackground('#000000') //optional
    ->setBlur(0) //optional, use values between 0 and 100
    ->setBrightness(0) //optional, use values between -100 and +100. brightness 0 for no change
    ->setGreyscale(true) //optional true or false default is false
    ->setOptimize(true) //optional
    ->setEncodeFormat() //optional, ['jpeg', 'jpg', 'png', 'gif', 'webp']
    ->setEncodeQuality() //optional, use values between 0 and 100
    ->save('resize'); //save option resize, crop default is resize
```

**Make new sizes from image**

```php
use Fynduck\FilesUpload\ManipulationImage;

ManipulationImage::load($pathImage)
            ->setDisk('storage') //default is public
            ->setFolder('Post')
            ->setSizes(['xs' => ['width' => 100, 'height' => 100]])
            ->setName('image_name.jpg') //name with extension
            ->setOverwrite('old_name.jpg') //optional, remove file with old name
            ->setBackground('#000000') //optional
            ->setBlur(0) //optional, use values between 0 and 100
            ->setBrightness(0) //optional, use values between -100 and +100. brightness 0 for no change
            ->setGreyscale(true) //optional true or false default is true
            ->setOptimize(true) //optional
            ->setEncodeFormat() //optional, ['jpeg', 'jpg', 'png', 'gif', 'webp']
            ->setEncodeQuality() //optional, use values between 0 and 100
            ->save('resize'); //save option resize, resize-crop, crop default is resize
```

**Optimize exist image**

```php
use Fynduck\FilesUpload\ManipulationImage;

ManipulationImage::load('image_name.jpg')
            ->setOptimize(true)
            ->optimize('path_to_image');
```

> **resize**: Resize the image by the maximum width or height
> **crop**: Cut out by size part of the current image with given width and height

## For laravel < 5.7 use version 1.8

## Use for previous versions

* [Version ^3.1](https://github.com/fynduck/files-upload/tree/3.1.7)
* [Version ^2.1](https://github.com/fynduck/files-upload/tree/2.1.3)
* [Version ^1.8](https://github.com/fynduck/files-upload/tree/1.8.6.2)

## Install

`composer require fynduck/files-upload`

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

<a href="https://www.jetbrains.com/?from=files-upload">
<img src="/phpstorm.png" alt="JetBrains" width="50"/>
</a>

## License

The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.
