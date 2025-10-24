<?php

namespace Shm\ShmBlueprints\FileUpload;


use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\FFProbe;
use Shm\ShmUtils\Response;



class ShmAudioUpload
{



    public function make(): array
    {



        return [
            'type' => Shm::fileAudio(),
            'formData' => true,

            'resolve' => function ($root, $args) {
                // Check if file is uploaded
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new \Exception('No file uploaded or upload error occurred');
                }

                $file = $_FILES['file'];

                $name = md5($file['name'] . time());


                $path = ShmFileUploadUtils::rootPath("audio");

                $filename = $name . '.' . pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp3';




                ShmFileUploadUtils::move($file,  $path, $filename);



                // Пути
                $sourceFile = $path . '/' . $filename;
                $convertedMp3 = $path . '/' . $name . '.mp3';

                // Конвертация
                $ffmpeg = FFMpeg::create();
                $audio = $ffmpeg->open($sourceFile);

                $format = new Mp3();
                $format->setAudioKiloBitrate(32);
                $format->setAudioChannels(1);

                $audio->save($format, $convertedMp3);

                // Получение длительности через ffprobe
                $ffprobe = FFProbe::create();
                $duration = (float) $ffprobe->format($convertedMp3)->get('duration');





                $url = ShmFileUploadUtils::saveToS3($convertedMp3, $name . '.mp3', "audios");


                $fields = [
                    "fileType" => "audio",
                    'name' => $name . '.mp3',
                    'url' =>  $url,
                    "type" => ShmFileUploadUtils::getMimeType($convertedMp3),

                    "size" => filesize($convertedMp3),
                    "duration" => $duration,
                    'owner' => Auth::getAuthOwner(),
                    'created_at' => time(),
                ];

                unlink($sourceFile);
                unlink($convertedMp3);

                $file = mDB::collection("_files")->insertOne($fields);
                $id = $file->getInsertedId();
                $fields['_id'] = (string) $id;

                return mDB::replaceObjectIdsToString($fields);
            }
        ];
    }
}
