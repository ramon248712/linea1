<?php
// Cargar PHPMailer desde la carpeta local
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Capturar los datos POST del bot (opcional, para uso futuro)
$app     = $_POST["app"] ?? '';
$sender  = $_POST["sender"] ?? '';
$message = $_POST["message"] ?? '';

// Crear nuevo mailer
$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP (completar con tus datos reales)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';         // ⚠️ Reemplazá con tu servidor SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'rgonzalezcuervoabogados@gmail.com';      // ⚠️ Tu usuario
    $mail->Password   = 'ppqf cyah kotw byki';          // ⚠️ Tu contraseña
    $mail->SMTPSecure = 'tls';                      // o 'ssl' si tu servidor lo requiere
    $mail->Port       = 587;                        // 465 si usás SSL

    // Remitente y destinatario
    $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
    $mail->addAddress('rgonzalezcuervoabogados@gmail.com', 'Destinatario');

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = 'Mensaje desde WhatsAuto';
    $mail->Body    = "Remitente: $sender<br>Mensaje: $message";
    $mail->AltBody = "Remitente: $sender\nMensaje: $message";

    $mail->send();

    // Devolver respuesta JSON para WhatsAuto
    echo json_encode([
        "reply" => "Gracias por tu mensaje. Te responderemos a la brevedad."
    ]);

} catch (Exception $e) {
    // En caso de error, también devolver un JSON válido
    echo json_encode([
        "reply" => "No se pudo enviar el correo. Error: {$mail->ErrorInfo}"
    ]);
}
