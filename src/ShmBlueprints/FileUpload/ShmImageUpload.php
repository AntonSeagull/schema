<?php

namespace Shm\ShmBlueprints\FileUpload;

use kornrunner\Blurhash\Blurhash;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;

use Shm\ShmUtils\Response;

class ShmImageUpload
{



    public function make(): array
    {



        return [
            'type' => Shm::fileImage(),


            'formData' => true,

            'resolve' => function ($root, $args) {


                $body = json_decode(file_get_contents('php://input'), true);

                $image = $_FILES['file'] ?? $_POST['file'] ?? $body['file'] ?? null;


                $width = $_REQUEST['w'] ?? $body['w'] ?? 1000;
                $height = $_REQUEST['h'] ?? $body['h'] ?? 1000;





                if ($image === null || (is_array($image) && !isset($image['tmp_name']))) {
                    Response::validation(
                        "Поле file обязательно для загрузки файла"
                    );
                }


                $path = ShmFileUploadUtils::rootPath("images");

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

                    ShmFileUploadUtils::move($image, $path, $filename);
                }

                $path = $path . '/' . $filename;

                $url = ShmFileUploadUtils::getResizeImage($path, $filename, $width, $height);

                $sizeOrigin = getimagesize($path);

                if ($sizeOrigin[0] > 1000) {
                    $url_filename_medium = ShmFileUploadUtils::getResizeImage($path, $filename_medium, 1000, 1000);
                } else {
                    $url_filename_medium = $url;
                }

                if ($sizeOrigin[0] > 500) {
                    $url_filename_small = ShmFileUploadUtils::getResizeImage($path, $filename_small, 500, 500);
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
                    'owner' => Auth::getAuthOwner(),
                    'name' => is_string($image) ? "none" : $image['name'],
                    'url' => $url,
                    'url_medium' => $url_filename_medium,
                    'url_small' => $url_filename_small,
                    'source' => "local",
                    "blurhash" => $blurhash,
                    'width' => $sizeOrigin[0],
                    'height' => $sizeOrigin[1],
                    "type" => ShmFileUploadUtils::getMimeType($path),
                    'created_at' => time(),
                ];



                $file = mDB::collection("_files")->insertOne($fields);
                $id = $file->getInsertedId();
                $fields['_id'] = $id;

                return $fields;
            }
        ];
    }
}
