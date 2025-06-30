<?php
// Configuración general
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

// Captura de datos
$sender = preg_replace('/\D/', '', $_POST["sender"] ?? "");
$message = trim($_POST["message"] ?? "");
$telefonoBase = substr($sender, -10);
$telefonoConPrefijo = "+549" . $telefonoBase;

$csv = "deudores.csv";
$respuesta = "";

// Función: Buscar deudor por teléfono
function buscarPorTelefono($telefono) {
    global $csv;
    if (!file_exists($csv)) return null;
    $fp = fopen($csv, "r");
    while (($line = fgetcsv($fp, 0, ";")) !== false) {
        if (count($line) >= 5) {
            $tel = preg_replace('/\D/', '', $line[2]);
            if (substr($tel, -10) === substr($telefono, -10)) {
                fclose($fp);
                return [
                    "nombre" => $line[0],
                    "dni" => $line[1],
                    "telefono" => $line[2],
                    "ejecutivo" => $line[3],
                    "tel_ejecutivo" => $line[4]
                ];
            }
        }
    }
    fclose($fp);
    return null;
}

// Función: Buscar por DNI y actualizar teléfono
function buscarPorDNI($dni, $nuevoTel) {
    global $csv;
    if (!file_exists($csv)) return null;

    $fp = fopen($csv, "r");
    $lineas = [];
    $encontrado = null;

    while (($line = fgetcsv($fp, 0, ";")) !== false) {
        if (count($line) >= 5) {
            if ($line[1] == $dni) {
                $line[2] = $nuevoTel;
                $encontrado = $line;
            }
            $lineas[] = $line;
        }
    }
    fclose($fp);

    if ($encontrado) {
        $fp = fopen($csv, "w");
        foreach ($lineas as $l) fputcsv($fp, $l, ";");
        fclose($fp);
        return [
            "nombre" => $encontrado[0],
            "dni" => $encontrado[1],
            "telefono" => $encontrado[2],
            "ejecutivo" => $encontrado[3],
            "tel_ejecutivo" => $encontrado[4]
        ];
    }
    return null;
}

// Función: Notificar al ejecutivo por correo
function notificarEjecutivo($nombreEjecutivo, $nombre, $dni, $telefono, $mensaje) {
    $email = strtolower($nombreEjecutivo) . "cuervoabogados@gmail.com";
    $asunto = "Nuevo mensaje de deudor: $nombre";
    $cuerpo = "📩 Mensaje recibido:\n\nNombre: $nombre\nDNI: $dni\nTeléfono: $telefono\n\nMensaje:\n$mensaje";
    $headers = "From: notificaciones@cuervoabogados.com";
    @mail($email, $asunto, $cuerpo, $headers);
}

// Función: Generar enlace WhatsApp al ejecutivo
function generarLink($deudor) {
    $telEjecutivo = preg_replace('/\D/', '', $deudor["tel_ejecutivo"]);
    $texto = "Hola {$deudor["ejecutivo"]}, soy *{$deudor["nombre"]}* (DNI: *{$deudor["dni"]}*), tengo una consulta";
    return "https://wa.me/54$telEjecutivo?text=" . urlencode($texto);
}

// Lógica principal
$deudor = buscarPorTelefono($telefonoConPrefijo);

if ($deudor) {
    $link = generarLink($deudor);
    $respuesta = "Hola {$deudor["nombre"]}, podés escribirle directamente a tu ejecutivo desde este enlace:\n$link";
    notificarEjecutivo($deudor["ejecutivo"], $deudor["nombre"], $deudor["dni"], $telefonoConPrefijo, $message);

} elseif (preg_match('/\b\d{7,8}\b/', $message, $coincidencias)) {
    $dni = $coincidencias[0];
    $nuevoTel = "+549" . substr(preg_replace('/\D/', '', $sender), -10);
    $deudor = buscarPorDNI($dni, $nuevoTel);

    if ($deudor) {
        $link = generarLink($deudor);
        $respuesta = "Hola {$deudor["nombre"]}, podés escribirle directamente a tu ejecutivo desde este enlace:\n$link";
        notificarEjecutivo($deudor["ejecutivo"], $deudor["nombre"], $deudor["dni"], $nuevoTel, $message);
    } else {
        $respuesta = "Hola. No encontramos deuda con ese DNI. ¿Podrías verificar si está bien escrito?";
    }

} else {
    $respuesta = "Hola. ¿Podrías indicarnos tu DNI (sin puntos) para identificarte?";
}

// Guardar historial
file_put_contents("historial_derivador.txt", date("Y-m-d H:i") . " | $sender => $message\n", FILE_APPEND);

// Devolver respuesta
echo json_encode(["reply" => $respuesta]);
exit;
?>
