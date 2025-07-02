<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

$app     = $_POST["app"] ?? '';
$sender  = preg_replace('/\D/', '', $_POST["sender"] ?? '');
$message = strtolower(trim($_POST["message"] ?? ''));
$senderBase = substr($sender, -10);

$csvFile = __DIR__ . '/deudores.csv';
$respondidosFile = __DIR__ . '/respondidos.json';

$deudores = [];
if (file_exists($csvFile)) {
    $file = fopen($csvFile, 'r');
    while (($data = fgetcsv($file, 0, ';')) !== false) {
        $deudores[] = [
            'nombre'     => $data[0] ?? '',
            'dni'        => $data[1] ?? '',
            'telefono'   => substr(preg_replace('/\D/', '', $data[2] ?? ''), -10),
            'ejecutivo'  => $data[3] ?? '',
            'tel_ejec'   => preg_replace('/\D/', '', $data[4] ?? ''),
        ];
    }
    fclose($file);
}

$respondidos = [];
if (file_exists($respondidosFile)) {
    $contenido = file_get_contents($respondidosFile);
    $decodificado = json_decode($contenido, true);
    if (is_array($decodificado)) {
        $respondidos = $decodificado;
    }
}

if (in_array($senderBase, $respondidos)) {
    echo json_encode(["reply" => "Contactá al encargado de tu gestión usando el link enviado anteriormente."]);
    exit;
}

foreach ($deudores as $row) {
    if ($row['telefono'] === $senderBase) {
        $respondidos[] = $senderBase;
        file_put_contents($respondidosFile, json_encode($respondidos));

        $link = "https://wa.me/54{$row['tel_ejec']}?text=" . urlencode("Hola {$row['ejecutivo']}, soy *{$row['nombre']}* (DNI: *{$row['dni']}*), tengo una consulta");
        $reply = "Hola {$row['nombre']}, podés escribirle directamente a tu ejecutivo desde este enlace:\n{$link}";
        echo json_encode(["reply" => $reply]);
        exit;
    }
}

if (preg_match('/^\d{7,8}$/', $message)) {
    $actualizado = false;

    foreach ($deudores as &$row) {
        if ($row['dni'] === $message && strlen($row['telefono']) < 5) {
            $row['telefono'] = $senderBase;
            $actualizado = true;

            $f = fopen($csvFile, 'w');
            foreach ($deudores as $d) {
                fputcsv($f, [$d['nombre'], $d['dni'], $d['telefono'], $d['ejecutivo'], $d['tel_ejec']], ';');
            }
            fclose($f);

            $respondidos[] = $senderBase;
            file_put_contents($respondidosFile, json_encode($respondidos));

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'rgonzalezcuervoabogados@gmail.com';
                $mail->Password   = 'ppqf cyah kotw byki';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
                $mail->addAddress('ejecutivocuervoabogados@gmail.com', 'Ejecutivo');

                $mail->Subject = 'Nuevo contacto de deudor';
                $mail->Body = "Número: $senderBase<br>DNI: {$message}<br>Mensaje: {$_POST["message"]}";
                $mail->isHTML(true);
                $mail->send();
            } catch (Exception $e) {}

            $link = "https://wa.me/54{$row['tel_ejec']}?text=" . urlencode("Hola {$row['ejecutivo']}, soy *{$row['nombre']}* (DNI: *{$row['dni']}*), tengo una consulta");
            $reply = "Hola {$row['nombre']}, podés escribirle directamente a tu ejecutivo desde este enlace:\n{$link}";
            echo json_encode(["reply" => $reply]);
            exit;
        }
    }

    if (!$actualizado) {
        echo json_encode(["reply" => "No encontramos tu DNI. Por favor, revisá que esté bien escrito (solo números)."]);
        exit;
    }
}

echo json_encode(["reply" => "Hola. Por favor, escribí tu DNI (solo números)."]);
exit;
