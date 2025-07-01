<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

function enviarCorreo($destino, $asunto, $cuerpo) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rgonzalezcuervoabogados@gmail.com'; // Tu Gmail
        $mail->Password = 'CLAVE_APP'; // Clave de aplicación (ver paso siguiente)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Remitente y destinatario
        $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Cuervo Bot');
        $mail->addAddress($destino);

        // Contenido
        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}
