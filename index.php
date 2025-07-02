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

if (strlen($senderBase) != 10) {
    echo json_encode(["reply" => ""]);
    exit;
}

$csvFile = __DIR__ . '/deudores.csv';
$respondidosFile = __DIR__ . '/respondidos.json';

$deudores = [];
if (file_exists($csvFile)) {
    $file = fopen($csvFile, 'r');
    while (($data = fgetcsv($file, 0, ';')) !== false) {
        if (count($data) < 5) continue;
        $deudores[] = [
            'nombre'     => $data[0],
            'dni'        => $data[1],
            'telefono'   => substr(preg_replace('/\D/', '', $data[2]), -10),
            'ejecutivo'  => $data[3],
            'tel_ejec'   => preg_replace('/\D/', '', $data[4]),
        ];
    }
    fclose($file);
}

$respondidos = file_exists($respondidosFile)
    ? json_decode(file_get_contents($respondidosFile), true)
    : [];

if (!is_array($respondidos)) $respondidos = [];

function enviarCorreo($nombre, $dni, $telefono) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rgonzalezcuervoabogados@gmail.com';
        $mail->Password   = 'ppqf cyah kotw byki'; // Usar variable de entorno si es posible
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
        $mail->addAddress('ejecutivocuervoabogados@gmail.com', 'Ejecutivo');

        $mail->Subject = 'Nuevo contacto de deudor';
        $mail->Body    = "Nombre: $nombre<br>DNI: $dni<br>Teléfono: $telefono";
        $mail->isHTML(true);
        $mail->send();
    } catch (Exception $e) {
        file_put_contents("mail_error_log.txt", date("Y-m-d H:i") . " | Mail error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
    }
}

// Paso 1: Ver si el número ya existe y enviar mensaje con link + correo
foreach ($deudores as $row) {
    if ($row['telefono'] === $senderBase) {
        if (!in_array($senderBase, $respondidos)) {
            $respondidos[] = $senderBase;
            file_put_contents($respondidosFile, json_encode($respondidos, JSON_PRETTY_PRINT));

            enviarCorreo($row['nombre'], $row['dni'], $senderBase);
        }

        $link = "https://wa.me/54{$row['tel_ejec']}?text=" . urlencode("Hola {$row['ejecutivo']}, soy *{$row['nombre']}* (DNI: *{$row['dni']}*), tengo una consulta");
        echo json_encode(["reply" => "Hola {$row['nombre']}, podés escribirle directamente a tu ejecutivo desde este enlace:\n{$link}"]);
        exit;
    }
}

// Paso 2: Ver si el mensaje es un DNI y actualizar CSV si corresponde
if (preg_match('/\b(\d{7,8})\b/', $message, $coinc)) {
    $dni = $coinc[1];
    $actualizado = false;

    foreach ($deudores as &$row) {
        if ($row['dni'] === $dni && $row['telefono'] !== $senderBase) {
            $row['telefono'] = $senderBase;
            $actualizado = true;

            $f = fopen($csvFile, 'w');
            foreach ($deudores as $d) {
                fputcsv($f, [$d['nombre'], $d['dni'], $d['telefono'], $d['ejecutivo'], $d['tel_ejec']], ';');
            }
            fclose($f);
        }
    }

    if ($actualizado) {
        // Volvemos a buscar para aplicar lógica como en paso 1
        foreach ($deudores as $row) {
            if ($row['telefono'] === $senderBase) {
                if (!in_array($senderBase, $respondidos)) {
                    $respondidos[] = $senderBase;
                    file_put_contents($respondidosFile, json_encode($respondidos, JSON_PRETTY_PRINT));

                    enviarCorreo($row['nombre'], $row['dni'], $senderBase);
                }

                $link = "https://wa.me/54{$row['tel_ejec']}?text=" . urlencode("Hola {$row['ejecutivo']}, soy *{$row['nombre']}* (DNI: *{$row['dni']}*), tengo una consulta");
                echo json_encode(["reply" => "Hola {$row['nombre']}, podés escribirle directamente a tu ejecutivo desde este enlace:\n{$link}"]);
                exit;
            }
        }
    } else {
        echo json_encode(["reply" => "No encontramos tu DNI. Por favor, revisá que esté bien escrito (solo números)."]);
        exit;
    }
}

// Si no es un número conocido ni un DNI válido
echo json_encode(["reply" => "Hola. Por favor, escribí tu DNI (solo números)."]);
exit;
