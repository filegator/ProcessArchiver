# ProcessArchiver
Alternative ZIP processing adapter for FileGator. It uses OS process to perform zip/unzip operations instead of php-zip extension.
Experimental, do not use on production.

## Installation

1. Go to filegator folder and install Proccess lib with composer:
```
composer require symfony/process
```

2. Put the `ProcessArchiver.php` file into filegator's folder `filegator/backend/Services/Archiver/Adapters/`


3. Replace your current `ArchiverInterface` section in your `configuration.php` file with this:

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
- Not tested on windows/mac


