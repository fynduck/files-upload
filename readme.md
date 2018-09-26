# FilesUpload

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/fynduck/files-upload.svg?style=flat-square)](https://packagist.org/packages/fynduck/files-upload)

## Install
`composer require fynduck/files-upload`

## Usage
```
$nameFile = PrepareFile::uploadFile('folder_save', 'type:_image_or_file', 'file_for_save', 'name_old_file', 'name_save_file', '['xs' => ['width' => 10, 'height' => 10]](optional)', 'save_format_ex:_png(optional)');

`function return name saved file`

## Testing
Run the tests with:

``` bash
vendor/bin/phpunit
```

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License
The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.
