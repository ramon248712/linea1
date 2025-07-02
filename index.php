<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

// 📩 Datos que envía WhatsAuto
$app     = $_POST["app"] ?? '';
$sender  = preg_replace('/\D/', '', $_POST["sender"] ?? '');
$message = strtolower(trim($_POST["message"] ?? ''));

// 🧠 Cargar base de deudores
$csvFile = __DIR__ . '/deudores.csv';
$respondidosFile = __DIR__ . '/respondidos.json';

// Cargar deudores
$deudores = [];
if (file_exists($csvFile)) {
    $file = fopen($csvFile, 'r');
    while (($data = fgetcsv($file, 0, ';')) !== false) {
        $deudores[] = [
            'nombre'     => $data[0] ?? '',
            'dni'        => $data[1] ?? '',
            'telefono'   => $data[2] ?? '',
            'ejecutivo'  => $data[3] ?? '',
            'tel_ejec'   => $data[4] ?? '',
        ];
    }
    fclose($file);
}

// Cargar historial de respondidos
$respondidos = file_exists($respondidosFile)
    ? json_decode(file_get_contents($respondidosFile), true)
    : [];

// 🚫 Si ya respondimos antes
if (in_array($sender, $respondidos)) {
    echo json_encode(["reply" => "Contacte al encargado de su gestión a través del link proporcionado."]);
    exit;
}

// 📍 Buscar si el teléfono ya está registrado en deudores
foreach ($deudores as $row) {
    if ($row['telefono'] === $sender) {
        // ➕ Marcar como respondido
        $respondidos[] = $sender;
        file_put_contents($respondidosFile, json_encode($respondidos));

        echo json_encode(["reply" => "Contacte al encargado de su gestión a través del link proporcionado."]);
        exit;
    }
}

// 📍 Si no está el número → ¿es un DNI?
if (preg_match('/^\d{7,8}$/', $message)) {
    $actualizado = false;

    foreach ($deudores as &$row) {
        if ($row['dni'] === $message && empty($row['telefono'])) {
            // 💾 Guardar nuevo teléfono
            $row['telefono'] = $sender;
            $actualizado = true;

            // ✅ Guardar el CSV actualizado
            $f = fopen($csvFile, 'w');
            foreach ($deudores as $d) {
                fputcsv($f, $d, ';');
            }
            fclose($f);

            // ➕ Marcar como respondido
            $respondidos[] = $sender;
            file_put_contents($respondidosFile, json_encode($respondidos));

            // 📩 Enviar correo al ejecutivo
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'rgonzalezcuervoabogados@gmail.com'; // tu Gmail
                $mail->Password   = 'ppqf cyah kotw byki';               // clave de app
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
                $mail->addAddress('ejecutivocuervoabogados@gmail.com', 'Ejecutivo');

                $mail->Subject = 'Nuevo contacto de deudor';
                $mail->Body = "Número: $sender<br>DNI: {$message}<br>Mensaje: {$_POST["message"]}";

                $mail->isHTML(true);
                $mail->send();
            } catch (Exception $e) {
                // no interrumpe la lógica si falla
            }

            // 🔗 Enviar link al deudor
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

// 🔁 Si no es número registrado ni DNI válido
echo json_encode(["reply" => "Hola. Por favor, escribí tu DNI (solo números)."]);
exit;
