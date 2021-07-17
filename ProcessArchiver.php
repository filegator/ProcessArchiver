<?php

/*
 * This file is part of the FileGator package.
 *
 * (c) Milos Stojanovic <alcalbg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 */

namespace Filegator\Services\Archiver\Adapters;

use Filegator\Services\Archiver\ArchiverInterface;
use Filegator\Services\Service;
use Filegator\Services\Storage\Filesystem as Storage;
use Symfony\Component\Process\Process;
use Filegator\Services\Tmpfs\TmpfsInterface;

class ProcessArchiver implements Service, ArchiverInterface
{
    protected $storage;

    protected $tmpfs;

    protected $tmp_path;

    protected $archive_dir;

    protected $zip_binary;

    protected $unzip_binary;

    public function __construct(TmpfsInterface $tmpfs)
    {
        $this->tmpfs = $tmpfs;
    }

    public function init(array $config = [])
    {
        $this->zip_binary = $config['zip_binary'];
        $this->unzip_binary = $config['unzip_binary'];

        $this->tmp_path = $config['tmp_path'];
        if (! is_dir($this->tmp_path)) {
            mkdir($this->tmp_path);
        }
    }

    public function createArchive(Storage $storage): string
    {
        $this->storage = $storage;

        $this->uniqid = uniqid();

        $this->archive_dir = $this->tmp_path.$this->uniqid;

        mkdir($this->archive_dir);

        return $this->uniqid;
    }

    public function addDirectoryFromStorage(string $path)
    {
        $content = $this->storage->getDirectoryCollection($path, true);
        mkdir($this->archive_dir.$path, 0777, true);

        foreach ($content->all() as $item) {
            if ($item['type'] == 'dir') {
                mkdir($this->archive_dir.$item['path'], 0777, true);
            }
            if ($item['type'] == 'file') {
                $this->addFileFromStorage($item['path']);
            }
        }
    }

    public function addFileFromStorage(string $path)
    {
        $file = $this->storage->readStream($path);

        $path_parts = pathinfo($this->archive_dir.$path);

        if (! file_exists($path_parts['dirname'])) {
            mkdir($path_parts['dirname'], 0777, true);
        }

        file_put_contents($this->archive_dir.$path, $file['stream']);
    }

    public function uncompress(string $source, string $destination, Storage $storage)
    {
        $uniqid = uniqid();

        $zipfile = $this->tmp_path.$uniqid.'.zip';
        $tmpdir = $this->tmp_path.$uniqid.'/';

        $remote_archive = $storage->readStream($source);
        file_put_contents($zipfile, $remote_archive['stream']);

        if (is_resource($remote_archive['stream'])) {
            fclose($remote_archive['stream']);
        }

        $command[] = $this->unzip_binary;
        $command[] = $zipfile;
        $command[] = '-d'; // unzip into directory
        $command[] = $tmpdir;

        $process = new Process($command);
        $process->setWorkingDirectory($this->tmp_path);
        $process->mustRun();

        $matches = self::rsearch($tmpdir, '/.*/');
        if (! empty($matches)) {
            foreach ($matches as $item) {
                $path_parts = pathinfo($item);
                $dirname = substr($path_parts['dirname'], strlen($tmpdir));
                if ($path_parts['basename'] == '.' || $path_parts['basename'] == '..') {
                    continue;
                }
                if (is_dir($item)) {
                    $storage->createDir($destination, $dirname.'/'.$path_parts['basename']);
                }
                if (is_file($item)) {
                    $stream = fopen($item, 'r');
                    $storage->store($destination.'/'.$dirname, basename($item), $stream);
                }
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        // cleanup
        unlink($zipfile);
        self::deletedir($tmpdir);
    }

    public function closeArchive()
    {
        $command[] = $this->zip_binary;
        $command[] = '-r'; // recurse into directories
        $command[] = '-m'; // move, delete OS files
        $command[] = $this->uniqid.'.zip';
        $command[] = '.';

        $process = new Process($command);
        $process->setWorkingDirectory($this->archive_dir);
        $process->mustRun();

        // cleanup
        rename($this->archive_dir.'/'.$this->uniqid.'.zip', $this->tmp_path.$this->uniqid.'.zip');
        unlink($this->archive_dir.'/'.$this->uniqid.'.zip');
        rmdir($this->archive_dir);

        // copy to real tmp (without .zip extension) so we can use it elsewhere
        $stream = fopen($this->tmp_path.'/'.$this->uniqid.'.zip', 'r');
        $this->tmpfs->write($this->uniqid, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        // final cleanup
        unlink($this->tmp_path.'/'.$this->uniqid.'.zip');
    }

    public function storeArchive($destination, $name)
    {
        $this->closeArchive();

        $file = $this->tmpfs->readStream($this->uniqid);
        $this->storage->store($destination, $name, $file['stream']);
        if (is_resource($file['stream'])) {
            fclose($file['stream']);
        }

        $this->tmpfs->remove($this->uniqid);
    }

    private static function rsearch($folder, $regPattern) {
        $dir = new \RecursiveDirectoryIterator($folder);
        $ite = new \RecursiveIteratorIterator($dir);
        $files = new \RegexIterator($ite, $regPattern, \RegexIterator::GET_MATCH);
        $fileList = array();
        foreach($files as $file) {
            $fileList = array_merge($fileList, $file);
        }
        return $fileList;
    }

    private static function deletedir($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (! self::deletedir($dir.'/'.$item)) {
                return false;
            }

        }

        return rmdir($dir);
    }

}
