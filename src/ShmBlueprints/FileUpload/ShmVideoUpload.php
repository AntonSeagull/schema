<?php

namespace Shm\ShmBlueprints\FileUpload;

use kornrunner\Blurhash\Blurhash;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;

use Shm\ShmUtils\Response;

use Error;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;


use maximal\audio\Waveform;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;
use Exception;
use FFMpeg\Format\Audio\Mp3;
use Aws\S3\S3Client;

use Shm\ShmDB\mDBLite;

class ShmVideoUpload
{



    public function make(): array
    {



        return [
            'type' => Shm::fileVideo(),


            'formData' => true,

            'resolve' => function ($root, $args) {


                $video_file = $_FILES['file'];

                $name = md5($video_file['name'] . time());

                $path = ShmFileUploadUtils::rootPath("videos");


                $filename = $name . '.' . pathinfo($video_file['name'], PATHINFO_EXTENSION) ?: 'mp4';
                $filename_cover = $name . '_cover.png';

                ShmFileUploadUtils::move($video_file,  $path, $filename);

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

                $mime = ShmFileUploadUtils::getMimeType($path . '/' . $filename);

                $url = ShmFileUploadUtils::saveToS3($path . '/' . $filename, $filename, "videos");
                unlink($path . '/' . $filename);

                $url_cover = ShmFileUploadUtils::saveToS3($path . '/' . $filename_cover, $filename_cover, "videos");
                unlink($path . '/' . $filename_cover);



                $fields = [

                    "fileType" => "video",
                    'owner' => Auth::getAuthOwner(),
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


                if ($size[0] > 360) {
                    /*    Cmd::doBackground("resizeVideo", [
                "_id" => $fields['_id']
            ]);*/
                }

                return $fields;
            }
        ];
    }
}
