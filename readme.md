# FilesUpload

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)
[![Total Downloads](https://img.shields.io/packagist/dt/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)

| **Laravel**  |  **files-upload** | **Php version** |
|---|---|---|
| ``<`` 5.7   | ``^``1.8  | ``>=``5.6
| ``>=`` 5.7  | ``^``2.0 | ``>=``5.6
| ``>=`` 5.7  | ``^``3.0 | ``>=``7.1

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
    ->setBlur() //optional, use values between 0 and 100
    ->setBrightness() //optional, use values between -100 and +100. brightness 0 for no change
    ->setGreyscale() //optional
    ->save();
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
            ->setBlur() //optional, use values between 0 and 100
            ->setBrightness() //optional, use values between -100 and +100. brightness 0 for no change
            ->setGreyscale() //optional
            ->save();
```

## For laravel < 5.7 use version 1.8

## Use for previous versions

* [Version ^2.0](https://github.com/fynduck/files-upload/tree/2.1.3)
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
