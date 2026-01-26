<?php


namespace Shm\ShmTypes\CompositeTypes\FileTypes\Utils;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\FrameRate;
use FFMpeg\Format\Video\X264;
use Exception;
use FFMpeg\FFMpeg;
use Shm\ShmBlueprints\FileUpload\ShmFileUploadUtils;
use Shm\ShmUtils\ShmInit;

class ResizeVideo
{


    private $videoUrl;
    private $newWidth = 320;
    private $videoKilobitrate = 300;
    private $audioKilobitrate = 32;
    private $audioChannels = 1;


    public function __construct($videoUrl, $newWidth = 320, $videoKilobitrate = 100, $audioKilobitrate = 32, $audioChannels = 1)
    {
        $this->videoUrl = $videoUrl;
        $this->newWidth = $newWidth;
        $this->videoKilobitrate = $videoKilobitrate;
        $this->audioKilobitrate = $audioKilobitrate;
        $this->audioChannels = $audioChannels;
    }


    public function resize()
    {

        $videoUrl = $this->videoUrl;
        $newWidth = $this->newWidth;
        $videoKilobitrate = $this->videoKilobitrate;
        $audioKilobitrate = $this->audioKilobitrate;
        $audioChannels = $this->audioChannels;

        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open($videoUrl);

        $videoDimensions = $video->getStreams()->videos()->first()->getDimensions();

        $newHeight = round(intval(($videoDimensions->getHeight() / $videoDimensions->getWidth()) * $newWidth) / 2) * 2;


        $duration = $video->getStreams()->videos()->first()->get('duration');

        $video->filters()
            ->resize(new Dimension($newWidth, $newHeight))
            ->framerate(new FrameRate(30), 30) // 30 FPS, GOP = 30
            ->synchronize();


        $format = new X264('aac', 'libx264');

        $format->setKiloBitrate($videoKilobitrate) // Ещё более низкий битрейт для видео
            ->setAudioChannels($audioChannels) // Моно аудио
            ->setAudioKiloBitrate($audioKilobitrate); // Низкий битрейт для аудио



        $tempVideoPath = ShmInit::$rootDir . '/storage/files/videos/' . md5(time() . '-' . rand(1111, 9999)) . '.mp4';



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

        $resutlUrl = ShmFileUploadUtils::saveToS3($tempVideoPath, md5(time() . '-' . rand(1111, 9999)) . '.mp4', 'videos');

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
}
