<?php
ini_set('display_errors', 0);
error_reporting(0);
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

$sender = preg_replace('/\D/', '', $_POST["sender"] ?? "");
$message = strtolower(trim($_POST["message"] ?? ""));
$telefonoBase = substr($sender, -10);
$telefonoConPrefijo = "+549" . $telefonoBase;

function buscarDeudor($telefono) {
    if (!file_exists("deudores.csv")) return null;
    $fp = fopen("deudores.csv", "r");
    $telBase = substr(preg_replace('/\D/', '', $telefono), -10);
    while (($line = fgetcsv($fp, 0, ";")) !== false) {
        if (count($line) >= 5) {
            $telCsv = substr(preg_replace('/\D/', '', $line[4]), -10);
            if ($telCsv === $telBase) {
                fclose($fp);
                return [
                    "nombre" => $line[0],
                    "dni" => $line[1],
                    "telefono" => $line[2],
                    "ejecutivo" => $line[3],
                    "whatsapp" => $line[4]
                ];
            }
        }
    }
    fclose($fp);
    return null;
}

function asociarDni($telefono, $dni) {
    $telefono = preg_replace('/\D/', '', $telefono);
    $nuevo = "+549" . substr($telefono, -10);
    $lineas = [];
    $encontrado = null;
    $fp = fopen("deudores.csv", "r");
    while (($line = fgetcsv($fp, 0, ";")) !== false) {
        if (count($line) >= 5) {
            if ($line[1] == $dni) {
                $line[4] = $telefono;
                $encontrado = $line;
            }
            $lineas[] = $line;
        }
    }
    fclose($fp);
    if ($encontrado) {
        $fp = fopen("deudores.csv", "w");
        foreach ($lineas as $line) {
            fputcsv($fp, $line, ";");
        }
        fclose($fp);
    }
    return $encontrado;
}

function notificarPorCorreo($deudor) {
    $to = "rgonzalezcuervoabogados@gmail.com";
    $subject = "📩 Nuevo contacto en línea 1";
    $message = "El deudor {$deudor['nombre']} (DNI: {$deudor['dni']}) se comunicó a la línea 1.\nTeléfono: {$deudor['whatsapp']}";
    $headers = "From: notificaciones@cuervoabogados.com";

    // Guardamos intento de envío para control
    file_put_contents("log_email.txt", date("Y-m-d H:i") . " - Enviando correo a $to\n", FILE_APPEND);

    return mail($to, $subject, $message, $headers);
}

$respuesta = "";
$deudor = buscarDeudor($telefonoConPrefijo);

if ($deudor) {
    $nombre = ucfirst(strtolower($deudor["nombre"]));
    $dni = $deudor["dni"];
    $ejecutivo = $deudor["ejecutivo"];
    $wa = preg_replace('/\D/', '', $deudor["whatsapp"]);

    $mensajeWa = "Hola $ejecutivo, soy *$nombre* (DNI: *$dni*), tengo una consulta";
    $url = "https://wa.me/549$wa?text=" . urlencode($mensajeWa);

    $respuesta = "Hola $nombre, podés escribirle directamente a tu ejecutivo desde este enlace:\n$url";

    // Solo si es línea 1 (por su número en el CSV)
    if ($wa === "1170587681") {
        notificarPorCorreo($deudor);
    }

} elseif (preg_match('/\b\d{7,8}\b/', $message, $coincidencia)) {
    $dni = $coincidencia[0];
    $deudorAsociado = asociarDni($telefonoBase, $dni);
    if ($deudorAsociado) {
        $nombre = ucfirst(strtolower($deudorAsociado[0]));
        $ejecutivo = $deudorAsociado[3];
        $wa = preg_replace('/\D/', '', $deudorAsociado[4]);

        $mensajeWa = "Hola $ejecutivo, soy *$nombre* (DNI: *$dni*), tengo una consulta";
        $url = "https://wa.me/549$wa?text=" . urlencode($mensajeWa);

        $respuesta = "Hola $nombre, podés escribirle directamente a tu ejecutivo desde este enlace:\n$url";

        if ($wa === "1170587681") {
            notificarPorCorreo([
                "nombre" => $nombre,
                "dni" => $dni,
                "whatsapp" => $wa
            ]);
        }

        // 🔴 Esto evita que siga procesando y mande otro mensaje más
        echo json_encode(["reply" => $respuesta]);
        exit;
    } else {
        $respuesta = "No encontramos deuda con ese DNI. ¿Podrías verificar si está bien escrito?";
        echo json_encode(["reply" => $respuesta]);
        exit;
    }
} else {
    $respuesta = "Hola. ¿Podrías indicarnos tu DNI (sin puntos) para identificarte?";
    echo json_encode(["reply" => $respuesta]);
    exit;
}
