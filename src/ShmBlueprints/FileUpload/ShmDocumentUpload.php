<?php

namespace Shm\ShmBlueprints\FileUpload;

use kornrunner\Blurhash\Blurhash;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;

use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;

class ShmDocumentUpload
{



    public function make(): array
    {



        return [
            'type' => Shm::fileDocument(),

            'formData' => true,

            'resolve' => function ($root, $args) {


                $document = $_FILES['file'];

                $name = md5($document['name'] . time());

                $path = ShmFileUploadUtils::rootPath("document");

                $filename = $name . '.' . pathinfo($document['name'], PATHINFO_EXTENSION);

                ShmFileUploadUtils::move($document, $path, $filename);





                $url = ShmFileUploadUtils::saveToS3($path . '/' . $filename, $filename, "files");






                $fields = [
                    "fileType" => "document",
                    'owner' => Auth::getAuthOwner(),
                    'name' => $document['name'],
                    'url' => $url,
                    'source' => "local",
                    "type" => ShmFileUploadUtils::getMimeType($path . '/' . $filename),
                    'created_at' => time(),
                ];
                unlink($path . '/' . $filename);


                $file = mDB::collection("_files")->insertOne($fields);
                $id = $file->getInsertedId();
                $fields['_id'] = (string) $id;

                return $fields;
            }
        ];
    }
}
