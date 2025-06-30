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
                $line[4] = $telefono; // Actualiza el WhatsApp
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
    $subject = "Nuevo contacto en línea 1";
    $message = "El deudor {$deudor['nombre']} (DNI: {$deudor['dni']}) se comunicó a la línea 1.\nTeléfono: {$deudor['whatsapp']}";
    $headers = "From: notificaciones@cuervoabogados.com";

    mail($to, $subject, $message, $headers);
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

    // Si es la línea 1 (Rami), notificamos
    if ($telefonoBase == "1170587681") {
        notificarPorCorreo($deudor);
    }

} elseif (preg_match('/\b\d{7,8}\b/', $message, $coincidencia)) {
    $dni = $coincidencia[0];
    $deudor = asociarDni($telefonoBase, $dni);
    if ($deudor) {
        $nombre = ucfirst(strtolower($deudor[0]));
        $ejecutivo = $deudor[3];
        $wa = preg_replace('/\D/', '', $deudor[4]);

        $mensajeWa = "Hola $ejecutivo, soy *$nombre* (DNI: *$dni*), tengo una consulta";
        $url = "https://wa.me/549$wa?text=" . urlencode($mensajeWa);

        $respuesta = "Hola $nombre, podés escribirle directamente a tu ejecutivo desde este enlace:\n$url";

        if ($telefonoBase == "1170587681") {
            notificarPorCorreo([
                "nombre" => $nombre,
                "dni" => $dni,
                "whatsapp" => $wa
            ]);
        }
    } else {
        $respuesta = "No encontramos deuda con ese DNI. ¿Podrías verificar si está bien escrito?";
    }
} else {
    $respuesta = "Hola. ¿Podrías indicarnos tu DNI (sin puntos) para identificarte?";
}

echo json_encode(["reply" => $respuesta]);
exit;
