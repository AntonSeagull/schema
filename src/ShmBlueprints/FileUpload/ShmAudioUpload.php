<?php

namespace Shm\ShmBlueprints\FileUpload;


use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;

use Shm\ShmUtils\Response;

use maximal\audio\Waveform;

class ShmAudioUpload
{



    public function make(): array
    {



        return [
            'type' => Shm::structure([
                "fileType" =>  Shm::string(),
                'name' =>  Shm::string(),
                'url' => Shm::string(),
                'source' =>  Shm::string(),
                "type" => Shm::string(),
                'created_at' => Shm::string(),
                "_id" => Shm::ID()
            ])->staticBaseTypeName("AudioFileUpload"),

            'formData' => true,

            'resolve' => function ($root, $args) {


                $file = $_FILES['file'];

                $name = md5($file['name'] . time());


                $path = ShmFileUploadUtils::rootPath("audio");

                $filename = $name . '.' . pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp3';

                ShmFileUploadUtils::move($file,  $path, $filename);

                $waveform = new Waveform($path . '/' . $filename);
                $data = $waveform->getWaveformData(10, true);
                $wave = $data['lines1'];
                $duration = (float) $waveform->getDuration();



                $url = ShmFileUploadUtils::saveToS3($path . '/' . $filename, $filename, "audios");
                unlink($path . '/' . $filename);


                $fields = [
                    "fileType" => "audio",
                    'name' => $file['name'],
                    'url' =>  $url,
                    "type" => ShmFileUploadUtils::getMimeType($path . '/' . $filename),
                    "wave" => $wave,
                    "duration" => $duration,
                    'user' => Auth::getAuthOwner(),
                    'created_at' => time(),
                ];

                $file = mDB::collection("_files")->insertOne($fields);
                $id = $file->getInsertedId();
                $fields['_id'] = (string) $id;

                return mDB::replaceObjectIdsToString($fields);
            }
        ];
    }
}
