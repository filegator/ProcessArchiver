# ProcessArchiver
Alternate ZIP processing adapter for FileGator.

## Installation

Install Symfony component Proccess with composer:
```
composer require symfony/process
```

Put the `ProcessArchiver.php` file into filegator's folder `filegator/backend/Services/Archiver/Adapters/`


Replace your current `ArchiverInterface` section in your `configuration.php` file with this:

```
        'Filegator\Services\Archiver\ArchiverInterface' => [
            'handler' => '\Filegator\Services\Archiver\Adapters\ProcessArchiver',
            'config' => [
                'zip_binary' => 'zip',      // or '/usr/bin/zip'
                'unzip_binary' => 'unzip',  // or '/usr/bin/unzip'
                'tmp_path' => __DIR__.'/private/tmp/',
            ],
        ],

```


## Known issues

- UTF8 file names encoding can be garbled


