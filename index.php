<?php 
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

// 📩 Datos de entrada
$app     = $_POST["app"] ?? '';
$sender  = preg_replace('/\D/', '', $_POST["sender"] ?? '');
$message = strtolower(trim($_POST["message"] ?? ''));

// Archivos
$csvFile = __DIR__ . '/deudores.csv';
$respondidosFile = __DIR__ . '/respondidos.json';

// Normalizar número
$telefonoBase = substr($sender, -10);
$telefonoConPrefijo = "549" . $telefonoBase;

// Cargar CSV
$deudores = [];
if (file_exists($csvFile)) {
    $file = fopen($csvFile, 'r');
    while (($data = fgetcsv($file, 0, ';')) !== false) {
        $deudores[] = [
            'nombre'     => $data[0] ?? '',
            'dni'        => $data[1] ?? '',
            'telefono'   => preg_replace('/\D/', '', $data[2] ?? ''),
            'ejecutivo'  => $data[3] ?? '',
            'tel_ejec'   => preg_replace('/\D/', '', $data[4] ?? ''),
        ];
    }
    fclose($file);
}

// Cargar lista de respondidos
$respondidos = file_exists($respondidosFile)
    ? json_decode(file_get_contents($respondidosFile), true)
    : [];

// Si ya se respondió
if (in_array($telefonoConPrefijo, $respondidos)) {
    echo json_encode(["reply" => "Contactá al ejecutivo desde el enlace que te enviamos anteriormente."]);
    exit;
}

// Buscar por teléfono
foreach ($deudores as $row) {
    if (substr($row['telefono'], -10) === $telefonoBase) {
        $respondidos[] = $telefonoConPrefijo;
        file_put_contents($respondidosFile, json_encode($respondidos));

        // Enviar mail
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rgonzalezcuervoabogados@gmail.com';
            $mail->Password   = 'ppqf cyah kotw byki';  // Clave de app
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
            $mail->addAddress('ejecutivocuervoabogados@gmail.com', 'Ejecutivo');

            $mail->Subject = 'Nuevo contacto de deudor';
            $mail->Body = "📩 El deudor {$row['nombre']} (DNI {$row['dni']}) se contactó desde el número +54{$telefonoBase}.";

            $mail->isHTML(true);
            $mail->send();
        } catch (Exception $e) {
            // No interrumpe si falla
        }

        $link = "https://wa.me/54{$row['tel_ejec']}?text=" . urlencode("Hola {$row['ejecutivo']}, soy *{$row['nombre']}* (DNI: *{$row['dni']}*), tengo una consulta");
        $respuesta = "Hola {$row['nombre']}, podés escribirle directamente a tu ejecutivo desde este enlace:\n{$link}";
        echo json_encode(["reply" => $respuesta]);
        exit;
    }
}

// Buscar por DNI
if (preg_match('/^\d{7,8}$/', $message)) {
    foreach ($deudores as &$row) {
        if ($row['dni'] === $message && empty($row['telefono'])) {
            $row['telefono'] = $telefonoConPrefijo;

            $f = fopen($csvFile, 'w');
            foreach ($deudores as $d) {
                fputcsv($f, $d, ';');
            }
            fclose($f);

            $respondidos[] = $telefonoConPrefijo;
            file_put_contents($respondidosFile, json_encode($respondidos));

            // Enviar mail
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

                $mail->Subject = 'Nuevo contacto con DNI';
                $mail->Body = "📩 El deudor {$row['nombre']} (DNI {$row['dni']}) vinculó el número +54{$telefonoBase}.";

                $mail->isHTML(true);
                $mail->send();
            } catch (Exception $e) {}

            $link = "https://wa.me/54{$row['tel_ejec']}?text=" . urlencode("Hola {$row['ejecutivo']}, soy *{$row['nombre']}* (DNI: *{$row['dni']}*), tengo una consulta");
            $respuesta = "Hola {$row['nombre']}, podés escribirle directamente a tu ejecutivo desde este enlace:\n{$link}";
            echo json_encode(["reply" => $respuesta]);
            exit;
        }
    }

    echo json_encode(["reply" => "No encontramos tu DNI. Por favor, revisá que esté bien escrito (solo números)."]);
    exit;
}

// Si no se entiende el mensaje
echo json_encode(["reply" => "Hola. Por favor, escribí tu DNI (solo números)."]);
exit;
