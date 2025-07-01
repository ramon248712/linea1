<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

function EnviarCorreo($destino, $asunto, $cuerpo) {
    $correo = new PHPMailer(true);

    try {
        $correo->isSMTP();
        $correo->Host = 'smtp.gmail.com';
        $correo->SMTPAuth = true;
        $correo->Username = 'rgonzalezcuervoabogados@gmail.com';
        $correo->Password = 'ppqf cyah kotw byki';
        $correo->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $correo->Port = 587;

        $correo->setFrom('rgonzalezcuervoabogados@gmail.com', 'Cuervo Bot');
        $correo->addAddress($destino);

        $correo->Subject = $asunto;
        $correo->Body = $cuerpo;

        $correo->send();
    } catch (Exception $e) {
        file_put_contents("errores_mail.txt", "Error al enviar: " . $correo->ErrorInfo . "\n", FILE_APPEND);
    }
}
