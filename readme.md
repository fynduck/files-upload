# FilesUpload

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)
[![Total Downloads](https://img.shields.io/packagist/dt/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)

## Install

`composer require fynduck/files-upload`

| **Laravel**  |  **files-upload** | **Php version** |
|---|---|---|
| ``<`` 5.7   | 1.8  | ^5.6
| ``>=`` 5.7  | ^2.0 | ^5.6
| ``>=`` 5.7  | ^3.0 | ^7.1

## For laravel < 5.7 use version 1.8

## Usage

**Upload file or image**

```php
use Fynduck\FilesUpload\UploadFile;

UploadFile::file($request->file('file')) //or $request->get('base64') //(required)
    ->setFolder('Post') //(optional)
    ->setName('image_name') //(optional) use file name or random in case base64
    ->setOverwrite(false) //(optional)
    ->setSizes(['xs' => ['width' => 100, 'height' => 100]]) //(optional) if need other sizes
    ->setExtension('png') //(optional)
    ->setBackground('#000000') //(optional)
    ->save();
```

**Make new sizes from image**

```php
use Fynduck\FilesUpload\ManipulationImage;

ManipulationImage::load($pathImage)
            ->setFolder('Post')
            ->setSizes(['xs' => ['width' => 100, 'height' => 100]])
            ->setName('image_name.jpg')
            ->setBackground('#000000') //(optional)
            ->save();
```

## Usage for version below ^3

**Upload image**

```
$nameImg = PrepareFile::uploadFile('folder_save', 'image', 'image_save', 'name_old_img', 'name_save_file');
```

**Upload file**

```
$nameFile = PrepareFile::uploadFile('folder_save', 'file', 'file_save', 'name_old_file', 'name_save_file');
```

**Upload base64**

```
$nameFile = PrepareFile::uploadBase64('folder_save', 'file/image', 'file_save', 'name_old_file', 'name_save_file');
```

`function return name saved file`

### Optional params

> ###### send size array
> ````['xs' => ['width' => 10, 'height' => 10]]````
> ###### resize only by width or height
> ````['xs' => ['width' => 10, 'height' => null]]````

> ````['xs' => ['width' => null, 'height' => 10]]````
> ###### format save
> ```````png/jpeg/jpg....```````
>> ###### crop or resize
> ```````crop/resize```````
>> > ###### background on crop
> ```````#ff0000```````
> ###### disk save
> ```````default is public```````

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

<a href="https://www.jetbrains.com/?from=files-upload">
<img src="/phpstorm.png" alt="JetBrains" width="50"/>
</a>

## License

The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.
