<?php

namespace Shm\ShmUtils;

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

class FileUploader
{

    public static function init()
    {





        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


        if ($requestMethod === 'POST') {



            if ($requestUri === '/file/image/upload') {
                $result = self::image();
                Response::success($result);
            }

            if ($requestUri === '/file/video/upload') {
                $result = self::video();
                Response::success($result);
            }

            if ($requestUri === '/file/document/upload') {
                $result = self::document();
                Response::success($result);
            }

            if ($requestUri === '/file/audio/upload') {
                $result = self::audio();
                Response::success($result);
            }
        }





        /*
        Core::instance()->route('POST /file/@type/upload', function ($f3, $params): void {

            $file = FileUpload::init($params['type']);

            Response::json($file, 200);
        });


    public static function init(string $type): mixed
    {
        switch ($type) {
            case "image":
                return self::image();
            case "video":
                return self::video();
            case "document":
                return self::document();
            case "audio":
                return self::audio();
            default:
                throw new Error("Unknown type");
        }
    }

        
        switch ($type) {
            case "image":
                return self::image($file);
            case "video":
                return self::video();
            case "document":
                return self::document();
            case "audio":
                return self::audio();
            default:
                throw new Error("Unknown type");
        }*/
    }


    private static function onlyLetters($str)
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


    private static function saveToS3($path, $file, $type = "files")
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




    private static function rootPath(string $fin_dir)
    {


        $dir =  ShmInit::$rootDir . '/storage/files/' . $fin_dir;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true); // создать все вложенные папки
        }

        return $dir;
    }


    private static function getMimeType($file)
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



    public static function image()
    {


        $body = json_decode(file_get_contents('php://input'), true);

        $image = $_FILES['file'] ?? $body['file'] ?? null;


        $width = $_REQUEST['w'] ?? $body['w'] ?? 1000;
        $height = $_REQUEST['h'] ?? $body['h'] ?? 1000;




        if ($image === null || (is_array($image) && !isset($image['tmp_name']))) {
            Response::validation(
                "Поле file обязательно для загрузки файла"
            );
        }


        $path = self::rootPath("images");

        if (is_string($image)) {

            $name = md5(time() . rand(1111, 9999));

            $file = $image;

            $filename = $name . '.png';
            $filename_medium = $name . '_medium.png';
            $filename_small = $name . '_small.png';

            $photoBASE64 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file));

            file_put_contents($path . '/' . $filename, $photoBASE64);
        } else {

            $name = md5($image['name'] . time());

            $filename = $name . '.' . pathinfo($image['name'], PATHINFO_EXTENSION) ?: 'png';
            $filename_medium = $name . '_medium.' . pathinfo($image['name'], PATHINFO_EXTENSION) ?: 'png';
            $filename_small = $name . '_small.' . pathinfo($image['name'], PATHINFO_EXTENSION) ?: 'png';

            self::move($image, $path, $filename);
        }

        $path = $path . '/' . $filename;

        $url = self::getResizeImage($path, $filename, $width, $height);

        $sizeOrigin = getimagesize($path);

        if ($sizeOrigin[0] > 1000) {
            $url_filename_medium = self::getResizeImage($path, $filename_medium, 1000, 1000);
        } else {
            $url_filename_medium = $url;
        }

        if ($sizeOrigin[0] > 500) {
            $url_filename_small = self::getResizeImage($path, $filename_small, 500, 500);
        } else {
            $url_filename_small = $url;
        }

        $imageForBlur = imagecreatefromstring(file_get_contents($url_filename_small));
        $width = imagesx($imageForBlur);
        $height = imagesy($imageForBlur);

        $step = 1;
        if ($width > 100) {
            $step = round($width / 10);
        }

        $pixels = [];
        for ($y = 0; $y < $height; $y = $y + $step) {
            $row = [];
            for ($x = 0; $x < $width; $x = $x + $step) {
                $index = imagecolorat($imageForBlur, $x, $y);
                $colors = imagecolorsforindex($imageForBlur, $index);

                $row[] = [$colors['red'], $colors['green'], $colors['blue']];
            }
            $pixels[] = $row;
        }

        $components_x = 4;
        $components_y = 3;
        $blurhash = Blurhash::encode($pixels, $components_x, $components_y);




        $fields = [
            "fileType" => "image",
            'user' => Auth::getAuthId(),
            'name' => is_string($image) ? "none" : $image['name'],
            'url' => $url,
            'url_medium' => $url_filename_medium,
            'url_small' => $url_filename_small,
            'source' => "local",
            "blurhash" => $blurhash,
            'width' => $sizeOrigin[0],
            'height' => $sizeOrigin[1],
            "type" => self::getMimeType($path),
            'created_at' => time(),
        ];

        if (Config::driverIsMongoDBLite()) {
            $file = mDBLite::collection("_files")->insertOne($fields);
        } else {

            $file = mDB::collection("_files")->insertOne($fields);
        }
        $id = $file->getInsertedId();
        $fields['_id'] = (string) $id;

        return mDB::replaceObjectIdsToString($fields);
    }

    private static function getResizeImage($path, $saveFilename, $w, $h)
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

    public static function document()
    {

        $document = $_FILES['file'];

        $name = md5($document['name'] . time());

        $path = self::rootPath("document");

        $filename = $name . '.' . pathinfo($document['name'], PATHINFO_EXTENSION);

        self::move($document, $path, $filename);

        $url = self::saveToS3(ShmInit::$rootDir . '/' . $path . '/' . $filename, $filename, "files");



        $fields = [
            "fileType" => "document",
            'user' => Auth::getAuthId(),
            'name' => $document['name'],
            'url' => $url,
            'source' => "local",
            "type" => self::getMimeType($path . '/' . $filename),
            'created_at' => time(),
        ];

        if (file_exists(ShmInit::$rootDir . '/' . $path . '/' . $filename)) {
            unlink(ShmInit::$rootDir . '/' . $path . '/' . $filename);
        }

        $file = mDB::collection("_files")->insertOne($fields);
        $id = $file->getInsertedId();
        $fields['_id'] = (string) $id;

        return mDB::replaceObjectIdsToString($fields);
    }

    private static function move($file, $path, $originalName)
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

    public static function video()
    {

        $video_file = $_FILES['file'];

        $name = md5($video_file['name'] . time());

        $path = self::rootPath("videos");


        $filename = $name . '.' . pathinfo($video_file['name'], PATHINFO_EXTENSION) ?: 'mp4';
        $filename_cover = $name . '_cover.png';

        self::move($video_file,  $path, $filename);

        $ffmpeg = FFMpeg::create();

        $video = $ffmpeg->open($path . '/' . $filename);


        // Получение размеров видео
        $videoStream = $ffmpeg->getFFProbe()
            ->streams($path . '/' . $filename)
            ->videos()
            ->first();

        // Проверка ширины видео
        if ($videoStream->get('width') > 1024) {
            // Удаление загруженного файла
            unlink($path . '/' . $filename);
            // Возвращение ошибки

            Response::validation('Видео слишком широкое (' . $videoStream->get('width') . ' пикселей). Максимальная ширина - 1024 пикселей.');
        }


        $duration = (float) $ffmpeg->getFFProbe()->format($path . '/' . $filename)->get('duration');

        $video->frame(TimeCode::fromSeconds(1))->save($path . '/' . $filename_cover);

        $size = getimagesize($path . '/' . $filename_cover);

        $mime = self::getMimeType($path . '/' . $filename);

        $url = self::saveToS3($path . '/' . $filename, $filename, "videos");
        unlink($path . '/' . $filename);

        $url_cover = self::saveToS3($path . '/' . $filename_cover, $filename_cover, "videos");
        unlink($path . '/' . $filename_cover);



        $fields = [

            "fileType" => "video",
            'user' => Auth::getAuthId(),
            'name' => $video_file['name'],
            'url' => $url,
            'cover' => $url_cover,
            'duration' => $duration,
            'width' => $size[0],
            'height' => $size[1],
            "type" => $mime,
            'created_at' => time(),
        ];

        if ($size[0] <= 360) {
            $fields['url_medium'] = $url;
        }

        $file = mDB::collection("_files")->insertOne($fields);
        $id = $file->getInsertedId();
        $fields['_id'] = (string) $id;

        if ($fields['user']) {
            $fields['user'] = (string) $fields['user'];
        }

        if ($size[0] > 360) {
            /*    Cmd::doBackground("resizeVideo", [
                "_id" => $fields['_id']
            ]);*/
        }

        return mDB::replaceObjectIdsToString($fields);
    }

    public static function makeResizeVideo($videoUrl, $newWidth = 320, $videoKilobitrate = 300, $audioKilobitrate = 32, $audioChannels = 1)
    {

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $ffmpeg = FFMpeg::create();


        $video = $ffmpeg->open($videoUrl);

        $videoDimensions = $video->getStreams()->videos()->first()->getDimensions();

        $newHeight = round(intval(($videoDimensions->getHeight() / $videoDimensions->getWidth()) * $newWidth) / 2) * 2;


        $duration = $video->getStreams()->videos()->first()->get('duration');

        $video->filters()->resize(new Dimension($newWidth, $newHeight))->synchronize();


        $format = new X264('aac', 'libx264');

        $format->setKiloBitrate($videoKilobitrate) // Ещё более низкий битрейт для видео
            ->setAudioChannels($audioChannels) // Моно аудио
            ->setAudioKiloBitrate($audioKilobitrate); // Низкий битрейт для аудио

        $tempVideoPath =  ShmInit::$rootDir . '/' . 'storage/files/videos/' . md5(time() . '-' . rand(1111, 9999)) . '.mp4';



        try {
            $video->save($format, $tempVideoPath);
        } catch (Exception $e) {
            \Sentry\captureException($e);
            return false;
        }
        $newVideo = $ffmpeg->open($tempVideoPath);
        $newDuration = $newVideo->getStreams()->videos()->first()->get('duration');

        if (floor($newDuration) != floor($duration)) {
            return false;
        }

        $fileSize = filesize($tempVideoPath);
        if ($fileSize === false || $fileSize == 0) {
            return false;
        }

        $resutlUrl = self::saveToS3($tempVideoPath, md5(time() . '-' . rand(1111, 9999)) . '.mp4', 'videos');

        // Memory cleanup
        unset($video);
        unset($newVideo);
        unset($ffmpeg);
        unset($videoDimensions);
        unset($format);

        // Удаление временного файла
        unlink($tempVideoPath);

        return $resutlUrl;
    }

    public static function makeResizeAudio($audioUrl, $audioKilobitrate = 64, $audioChannels = 1)
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $ffmpeg = FFMpeg::create();

        $audio = $ffmpeg->open($audioUrl);




        $duration = $audio->getStreams()->audios()->first()->get('duration');

        $format = new Mp3(); // You can choose another format if needed

        $format->setAudioChannels($audioChannels) // Set the number of audio channels
            ->setAudioKiloBitrate($audioKilobitrate); // Set the audio bitrate


        $tempAudioPath =  ShmInit::$rootDir . '/' . 'storage/files/' . md5(time() . '-' . rand(1111, 9999)) . '.mp3';

        try {
            $audio->save($format, $tempAudioPath);
        } catch (Exception $e) {
            \Sentry\captureException($e);
            return false;
        }


        $newAudio = $ffmpeg->open($tempAudioPath);
        $newDuration = $newAudio->getStreams()->audios()->first()->get('duration');



        if (floor($newDuration) != floor($duration)) {
            return false;
        }

        $fileSize = filesize($tempAudioPath);
        if ($fileSize === false || $fileSize == 0) {
            return false;
        }


        $resultUrl = self::saveToS3($tempAudioPath, md5(time() . '-' . rand(1111, 9999)) . '.mp3', 'audios');

        // Memory cleanup
        unset($audio);
        unset($newAudio);
        unset($ffmpeg);
        unset($format);

        // Delete the temporary file
        unlink($tempAudioPath);

        return $resultUrl;
    }

    public function audio()
    {

        $file = $_FILES['file'];

        $name = md5($file['name'] . time());


        $path = self::rootPath("audio");

        $filename = $name . '.' . pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp3';

        self::move($file,  $path, $filename);

        $waveform = new Waveform($path . '/' . $filename);
        $data = $waveform->getWaveformData(10, true);
        $wave = $data['lines1'];
        $duration = (float) $waveform->getDuration();



        $url = self::saveToS3($path . '/' . $filename, $filename, "audios");
        unlink($path . '/' . $filename);


        $fields = [
            "fileType" => "audio",
            'name' => $file['name'],
            'url' =>  $url,
            "type" => self::getMimeType($path . '/' . $filename),
            "wave" => $wave,
            "duration" => $duration,
            'user' => Auth::getAuthId(),
            'created_at' => time(),
        ];

        $file = mDB::collection("_files")->insertOne($fields);
        $id = $file->getInsertedId();
        $fields['_id'] = (string) $id;

        return mDB::replaceObjectIdsToString($fields);
    }
}
