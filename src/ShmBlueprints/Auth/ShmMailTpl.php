<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmTwig;

class ShmMailTpl
{


  public static function successLogin($cancelKey): array
  {

    $scheme =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? "";

    $currentURL = $scheme . '://' . $host;


    $cancelUrl = $currentURL . "/account/session/revoke/" . $cancelKey;

    $icon = file_get_contents(ShmInit::$shmDir . '/../assets/mail/login.png');

    $iconBase64 = base64_encode($icon);
    $iconBase64 = 'data:image/png;base64,' . $iconBase64;


    $requestIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $body = ShmTwig::render('@shm/mail/success-login', [
      'iconBase64' => $iconBase64,
      'requestIp' => $requestIp,
      'cancelUrl' => $cancelUrl,
    ]);

    $subject = "Account sign-in notification / Уведомление о входе в аккаунт";

    return [
      $body,
      $subject
    ];
  }

  public static function tplRecoveryEmail($code): array
  {


    $icon = file_get_contents(ShmInit::$shmDir . '/../assets/mail/reset-password.png');

    $iconBase64 = base64_encode($icon);
    $iconBase64 = 'data:image/png;base64,' . $iconBase64;


    $requestIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $body = ShmTwig::render('@shm/mail/recovery-email', [
      'iconBase64' => $iconBase64,
      'code' => $code,
      'requestIp' => $requestIp,
    ]);

    $subject = "Password recovery / Восстановление пароля";

    return [
      $body,
      $subject
    ];
  }

  public static function tplConfirmationEmail($code): array
  {

    $requestIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $icon = file_get_contents(ShmInit::$shmDir . '/../assets/mail/reset-password.png');

    $iconBase64 = base64_encode($icon);
    $iconBase64 = 'data:image/png;base64,' . $iconBase64;

    $body = ShmTwig::render('@shm/mail/confirmation-email', [
      'iconBase64' => $iconBase64,
      'code' => $code,
      'requestIp' => $requestIp,
    ]);




    $subject = "Confirmation of registration / Подтверждение регистрации";


    return [
      $body,
      $subject
    ];
  }
}
