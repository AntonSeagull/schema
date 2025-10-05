<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;

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



    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account sign-in notification</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Resets */
body,table,td,a{ -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
table,td{ border-collapse:collapse !important; }
img{ -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
/* Base */
body{ margin:0; padding:0; width:100% !important; background:#f5f5f7; }
.wrapper{ width:100%; background:#f5f5f7; padding:24px 0; }
.container{ width:100%; max-width:600px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.04); }
.header{ padding:28px 28px 8px; text-align:left; }
.brand{ font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; font-size:18px; font-weight:600; color:#111111; letter-spacing:0.2px; }
.preheader{ display:none; font-size:1px; color:#ffffff; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; }
.content{ padding:0 28px 4px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; color:#111111; }
.h1{ font-size:22px; font-weight:700; margin:8px 0 4px; line-height:1.3; }
.sub{ font-size:13px; color:#6e6e73; margin:0 0 16px; }
.p{ font-size:16px; line-height:1.55; margin:0 0 16px; }
.meta{ font-size:13px; color:#6e6e73; margin:12px 0 0; }
.divider{ height:1px; background:#e5e5ea; margin:24px 0; }
.notice{ font-size:13px; color:#6e6e73; }
.footer{ padding:20px 28px 28px; text-align:left; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; color:#6e6e73; font-size:12px; }
.button{ display:inline-block; padding:12px 20px; font-size:15px; font-weight:600; color:#fff; background:#0071e3; border-radius:8px; text-decoration:none; margin:6px; }
@media (prefers-color-scheme: dark){
  body{ background:#000000; }
  .wrapper{ background:#000000; }
  .container{ background:#1c1c1e; box-shadow:none; }
  .brand,.h1,.p{ color:#ffffff !important; }
  .sub,.meta,.notice,.footer{ color:#a1a1a6 !important; }
  .divider{ background:#3a3a3c; }
  .button{ background:#0a84ff !important; }
}
</style>
</head>
<body>
<span class="preheader">A new login to your account was detected. If this wasn't you, secure your account immediately.</span>
<div class="wrapper">
  <table role="presentation" class="container" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="header">
         <img src="{$iconBase64}" width="64" height="64"  style="border-radius:20px; display:block;">
    </td></tr>
    <tr><td class="content">
      <div class="h1">New sign-in detected</div>
      <div class="sub">Новый вход в аккаунт</div>

      <p class="p">
        A login to your account has been detected.
        <br><span class="ru">Был зафиксирован вход в ваш аккаунт.</span>
      </p>

      <p class="p"><strong>Security details</strong><br>
        Sign-in IP: {$requestIp}<br>
        <span class="ru">IP-адрес входа: {$requestIp}</span>
      </p>

      <div class="divider"></div>

      <p class="p">
        If this was you, no action is needed.
        <br><span class="ru">Если это были вы, ничего делать не нужно.</span>
      </p>

      <p class="p">
        If you did not log in, your account may be compromised.  
        Please reset your password immediately and cancel this login:
        <br><span class="ru">Если это были не вы, ваш аккаунт может быть скомпрометирован. Срочно смените пароль и отмените этот вход:</span>
      </p>

      <p class="p">
        
        <a href="{$cancelUrl}">Cancel This Login / Отменить этот вход</a>
      
      </p>

      <p class="notice">
        Stay safe — Security Team  
        <br><span class="ru">Оставайтесь в безопасности — команда безопасности</span>
      </p>
    </td></tr>
    <tr><td class="footer">
      
    </td></tr>
  </table>
</div>
</body>
</html>
HTML;

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

    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Password recovery</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Client resets */
body,table,td,a{ -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
table,td{ mso-table-lspace:0pt; mso-table-rspace:0pt; border-collapse:collapse !important; }
img{ -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
/* Base */
body{ margin:0; padding:0; width:100% !important; background:#f5f5f7; }
.wrapper{ width:100%; background:#f5f5f7; padding:24px 0; }
.container{ width:100%; max-width:600px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.04); }
.header{ padding:28px 28px 8px; text-align:left; }
.brand{ font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; font-size:18px; font-weight:600; color:#111111; letter-spacing:0.2px; }
.preheader{ display:none; font-size:1px; color:#ffffff; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; }
.content{ padding:0 28px 4px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; color:#111111; }
.h1{ font-size:22px; font-weight:700; margin:8px 0 4px; line-height:1.3; }
.sub{ font-size:13px; color:#6e6e73; margin:0 0 16px; }
.p{ font-size:16px; line-height:1.55; margin:0 0 16px; }
.code-wrap{ margin:24px 0 16px; }
.code{ font-family:SFMono-Regular,Menlo,Consolas,monospace; font-size:28px; letter-spacing:2px; font-weight:700; text-align:center; padding:16px 20px; border:1px solid #e5e5ea; border-radius:12px; background:#fbfbfd; }
.meta{ font-size:13px; color:#6e6e73; margin:12px 0 0; }
.divider{ height:1px; background:#e5e5ea; margin:24px 0; }
.notice{ font-size:13px; color:#6e6e73; }
.footer{ padding:20px 28px 28px; text-align:left; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; color:#6e6e73; font-size:12px; }
@media (prefers-color-scheme: dark){
  body{ background:#000000; }
  .wrapper{ background:#000000; }
  .container{ background:#1c1c1e; box-shadow:none; }
  .brand,.h1,.p,.code{ color:#ffffff !important; }
  .sub,.meta,.notice,.footer{ color:#a1a1a6 !important; }
  .code{ background:#2c2c2e; border-color:#3a3a3c; }
  .divider{ background:#3a3a3c; }
}
</style>
</head>
<body>
<span class="preheader">Your verification code: {$code}. Valid for 15 minutes.</span>
<div class="wrapper">
  <table role="presentation" class="container" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="header">
      <img src="{$iconBase64}" width="64" height="64"  style="border-radius:20px; display:block;">
      
    </td></tr>
    <tr><td class="content">
      <div class="h1">Password recovery</div>
      <div class="sub">Восстановление пароля</div>

      <p class="p">
        You requested to recover your password. To continue, enter this confirmation code:
        <br><span class="ru">Вы запросили восстановление пароля. Для продолжения введите этот код подтверждения:</span>
      </p>

      <div class="code-wrap"><div class="code">{$code}</div></div>

      <p class="p">
        This code is valid for 15 minutes.
        <br><span class="ru">Этот код действителен в течение 15 минут.</span>
      </p>

      <div class="divider"></div>

      <p class="p"><strong>Security details</strong><br>
        Request IP: {$requestIp}<br>
        <span class="ru">IP-адрес запроса: {$requestIp}</span>
      </p>

      <p class="notice">
        If you did not request a password recovery, you can safely ignore this email.
        <br><span class="ru">Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.</span>
      </p>
    </td></tr>
    <tr><td class="footer">
      
    </td></tr>
  </table>
</div>
</body>
</html>
HTML;

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


    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Confirmation of registration</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Client resets */
body,table,td,a{ -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
table,td{ mso-table-lspace:0pt; mso-table-rspace:0pt; border-collapse:collapse !important; }
img{ -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
/* Base */
body{ margin:0; padding:0; width:100% !important; background:#f5f5f7; }
.wrapper{ width:100%; background:#f5f5f7; padding:24px 0; }
.container{ width:100%; max-width:600px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.04); }
.header{ padding:28px 28px 8px; text-align:left; }
.brand{ font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; font-size:18px; font-weight:600; color:#111111; letter-spacing:0.2px; }
.preheader{ display:none; font-size:1px; color:#ffffff; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; }
.content{ padding:0 28px 4px; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; color:#111111; }
.h1{ font-size:22px; font-weight:700; margin:8px 0 4px; line-height:1.3; }
.sub{ font-size:13px; color:#6e6e73; margin:0 0 16px; }
.p{ font-size:16px; line-height:1.55; margin:0 0 16px; }
.code-wrap{ margin:24px 0 16px; }
.code{ font-family:SFMono-Regular,Menlo,Consolas,monospace; font-size:28px; letter-spacing:2px; font-weight:700; text-align:center; padding:16px 20px; border:1px solid #e5e5ea; border-radius:12px; background:#fbfbfd; }
.meta{ font-size:13px; color:#6e6e73; margin:12px 0 0; }
.divider{ height:1px; background:#e5e5ea; margin:24px 0; }
.notice{ font-size:13px; color:#6e6e73; }
.footer{ padding:20px 28px 28px; text-align:left; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Helvetica Neue",Arial,sans-serif; color:#6e6e73; font-size:12px; }
@media (prefers-color-scheme: dark){
  body{ background:#000000; }
  .wrapper{ background:#000000; }
  .container{ background:#1c1c1e; box-shadow:none; }
  .brand,.h1,.p,.code{ color:#ffffff !important; }
  .sub,.meta,.notice,.footer{ color:#a1a1a6 !important; }
  .code{ background:#2c2c2e; border-color:#3a3a3c; }
  .divider{ background:#3a3a3c; }
}
</style>
</head>
<body>
<span class="preheader">Your confirmation code: {$code}. Valid for 15 minutes.</span>
<div class="wrapper">
  <table role="presentation" class="container" cellpadding="0" cellspacing="0" width="100%">
    <tr><td class="header">
     <img src="{$iconBase64}" width="64" height="64"  style="border-radius:20px; display:block;">
    </td></tr>
    <tr><td class="content">
      <div class="h1">Confirmation of registration</div>
      <div class="sub">Подтверждение регистрации</div>

      <p class="p">
        Thank you for registering! To complete your registration, enter this confirmation code:
        <br><span class="ru">Благодарим за регистрацию! Для завершения процесса введите следующий код подтверждения:</span>
      </p>

      <div class="code-wrap"><div class="code">{$code}</div></div>

      <p class="p">
        This code is valid for 15 minutes.
        <br><span class="ru">Этот код действителен в течение 15 минут.</span>
      </p>

      <div class="divider"></div>

      <p class="p"><strong>Security details</strong><br>
        Request IP: {$requestIp}<br>
        <span class="ru">IP-адрес запроса: {$requestIp}</span>
      </p>

      <p class="notice">
        If you did not sign up and received this email by mistake, ignore it.
        <br><span class="ru">Если вы не регистрировались и получили это письмо по ошибке, проигнорируйте его.</span>
      </p>
    </td></tr>
    <tr><td class="footer">
      
    </td></tr>
  </table>
</div>
</body>
</html>
HTML;




    $subject = "Confirmation of registration / Подтверждение регистрации";


    return [
      $body,
      $subject
    ];
  }
}
