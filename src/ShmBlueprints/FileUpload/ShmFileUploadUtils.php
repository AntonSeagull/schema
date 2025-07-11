<?php

namespace Shm\ShmBlueprints\FileUpload;

use Error;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use kornrunner\Blurhash\Blurhash;

use maximal\audio\Waveform;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;
use Exception;
use FFMpeg\Format\Audio\Mp3;
use Aws\S3\S3Client;

use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBLite;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;

class ShmFileUploadUtils
{

    public static function move($file, $path, $originalName)
    {
        $destinationPath = $path . '/' . $originalName;


        try {
            $result = move_uploaded_file($file['tmp_name'], $destinationPath);
        } catch (Exception $e) {
            \Sentry\captureException($e);
            Response::validation("Ошибка при загрузке файла");
        }
        if (!$result) {
            Response::validation("Ошибка при загрузке файла");
        }
    }

    public static function getResizeImage($path, $saveFilename, $w, $h)
    {

        if ($w && $h) {

            $image = new \Gumlet\ImageResize($path);

            $image->resizeToBestFit($w, $h);
            $tmp_path = $path . '-' . time();
            $image->save($tmp_path);


            $result = self::saveToS3($tmp_path, $saveFilename, "images");

            unlink($tmp_path);

            return $result;
        } else {
            return self::saveToS3($path, $saveFilename, "images");
        }
    }

    public static function onlyLetters($str)
    {
        $result = preg_replace('/[^a-zа-я]/ui', '*', $str);
        $result = explode('*', $result ?? "");
        $result = array_diff($result, ['']);

        $text = [];
        foreach ($result as $index => $val) {
            if ($index > 0) {
                $val = ucfirst($val);
            }

            $text[] = $val;
        }
        return implode('', $text);
    }


    public static function saveToS3($path, $file, $type = "files")
    {
        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version' => Config::get("s3.version", 'latest'),
            'region' => Config::get("s3.region"),
            'endpoint' => Config::get("s3.endpoint"),
            'credentials' => [
                'key' => Config::get("s3.credentials.key"),
                'secret' => Config::get("s3.credentials.secret"),
            ],
        ]);

        $rootDir = self::onlyLetters($_SERVER['HTTP_HOST'] ?? "cmd");

        // Check if $path is a URL.
        if (filter_var($path, FILTER_VALIDATE_URL)) {

            $new_path = ShmInit::$rootDir . '/storage/files/s3upload';

            if (!is_dir($new_path)) {
                mkdir($new_path, 0777, true); // создать все вложенные папки
            }


            file_put_contents($new_path, file_get_contents($path));
            $path = $new_path;
        }

        // If $path is not a URL, assume it is a file path and open the file.
        $body = fopen($path, 'r');
        $size = filesize($path);

        $configPutObject = [
            'Bucket' => Config::get("s3.bucket"),
            'Key' => $rootDir . "/" . $type . '/' . $file,
            'Body' => $body,
            'ContentLength' => $size,
            'ACL' => 'public-read',
        ];



        $result = $s3->putObject($configPutObject);

        // If the file was downloaded to a temporary location, delete it.
        if (isset($new_path)) {
            unlink($new_path);
        }

        if (isset($result['ObjectURL'])) {
            return $result['ObjectURL'];
        } else {
            return '';
        }
    }




    public static function rootPath(string $fin_dir)
    {


        $dir =  ShmInit::$rootDir . '/storage/files/' . $fin_dir;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true); // создать все вложенные папки
        }

        return $dir;
    }


    public static function getMimeType($file)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, $file);
            finfo_close($finfo);
        } else {
            $mimetype = mime_content_type($file);
        }
        if (empty($mimetype)) {
            $mimetype = 'application/octet-stream';
        }

        return $mimetype;
    }
}
