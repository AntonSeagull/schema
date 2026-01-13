<?php

namespace Shm\ShmAuth;

use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmTwig;

class AuthSessionRevoke
{

  public static function init()
  {

    //Если запрос пришел из CLI, то не меняем таймзону
    if (Cmd::cli()) {
      return;
    }



    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';


    $requestUri = is_string($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    $requestUri = rtrim($requestUri, '/');


    if ($requestMethod === 'GET' && preg_match('#^/account/session/revoke/([a-f0-9]{128})$#', $requestUri, $matches)) {

      $token = $matches[1];

      mDB::_collection(Auth::$token_collection)->deleteOne([
        'cancelKey' => $token
      ]);

      $html = ShmTwig::render('@shm/session-revoked');
      Response::html($html);
      exit;
    }
  }
}