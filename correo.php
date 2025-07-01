<?php
// Cargar PHPMailer desde carpeta local
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Capturar datos del POST
$app      = $_POST["app"] ?? '';
$sender   = $_POST["sender"] ?? '';
$message  = $_POST["message"] ?? '';

// Crear nuevo PHPMailer
$mail = new PHPMailer(true);

try {
    // Configurar servidor SMTP de Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'rgonzalezcuervoabogados@gmail.com'; // Tu email Gmail
    $mail->Password   = 'ppqf cyah kotw byki';               // Clave de aplicación
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Remitente y destinatario
    $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
    $mail->addAddress('rgonzalezcuervoabogados@gmail.com', 'Destinatario');

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = 'Mensaje desde WhatsAuto';
    $mail->Body    = "Remitente: $sender<br>Mensaje: $message";
    $mail->AltBody = "Remitente: $sender\nMensaje: $message";

    // Enviar
    $mail->send();

    // Respuesta JSON para WhatsAuto
    echo json_encode(["reply" => "Gracias por tu mensaje. Te contactaremos pronto."]);

} catch (Exception $e) {
    // Devolver error como JSON (WhatsAuto necesita JSON)
    echo json_encode(["reply" => "Error al enviar: {$mail->ErrorInfo}"]);
}
