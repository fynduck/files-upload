# FilesUpload

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)
[![Total Downloads](https://img.shields.io/packagist/dt/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)

## Install
`composer require fynduck/files-upload`

## Usage
```
$nameImg = PrepareFile::uploadFile('folder_save', 'image', 'image_save', 'name_old_img', 'name_save_file', '['xs' => ['width' => 10, 'height' => 10]](optional)', 'save_format_ex:png(optional)');
```

```
$nameFile = PrepareFile::uploadFile('folder_save', 'file', 'file_save', 'name_old_file', 'name_save_file');
```

`function return name saved file`

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License
The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.
