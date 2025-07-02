<?php
ini_set('display_errors', 0);
error_reporting(0);
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$app = $_POST["app"] ?? "";
$sender = preg_replace('/\D/', '', $_POST["sender"] ?? "");
$message = strtolower(trim($_POST["message"] ?? ""));

$telefonoBase = substr($sender, -10);
if (strlen($telefonoBase) != 10) exit(json_encode(["reply" => ""]));
$telefonoConPrefijo = "+549" . $telefonoBase;
if (strlen($message) < 3 || preg_match('/^[^a-zA-Z0-9]+$/', $message)) exit(json_encode(["reply" => ""]));

function saludoHora() {
    $h = (int)date("H");
    if ($h >= 6 && $h < 12) return "Buen día";
    if ($h >= 12 && $h < 19) return "Buenas tardes";
    return "Buenas noches";
}

function contiene($msg, $palabras) {
    foreach ($palabras as $p) {
        if (strpos($msg, $p) !== false) return true;
    }
    return false;
}

function normalizarTelefono($telCrudo) {
    $tel = preg_replace('/\D/', '', $telCrudo);
    return "+549" . substr($tel, -10);
}

function registrarVisita($telefono) {
    $visitas = [];
    if (file_exists("visitas.csv")) {
        foreach (file("visitas.csv") as $linea) {
            [$tel, $fecha] = str_getcsv($linea);
            $visitas[$tel] = $fecha;
        }
    }
    $visitas[$telefono] = date("Y-m-d");
    $fp = fopen("visitas.csv", "w");
    foreach ($visitas as $t => $f) fputcsv($fp, [$t, $f]);
    fclose($fp);
}

function buscarDeudor($tel) {
    if (!file_exists("deudores.csv")) return null;
    $fp = fopen("deudores.csv", "r");
    while (($line = fgetcsv($fp, 0, ";")) !== false) {
        if (count($line) >= 5) {
            $telefonoCSV = normalizarTelefono($line[2]);
            if (substr($telefonoCSV, -10) === substr($tel, -10)) {
                fclose($fp);
                return ["nombre" => $line[0], "dni" => $line[1], "telefono" => $telefonoCSV, "ejecutivo" => $line[3], "tel_ejecutivo" => $line[4]];
            }
        }
    }
    fclose($fp);
    return null;
}

function enviarEmail($sender, $message) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rgonzalezcuervoabogados@gmail.com';
        $mail->Password   = 'ppqf cyah kotw byki';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('rgonzalezcuervoabogados@gmail.com', 'Bot Legal');
        $mail->addAddress('rgonzalezcuervoabogados@gmail.com', 'Destinatario');

        $mail->isHTML(true);
        $mail->Subject = 'Mensaje desde WhatsAuto';
        $mail->Body    = "Remitente: $sender<br>Mensaje: $message";
        $mail->AltBody = "Remitente: $sender\nMensaje: $message";

        $mail->send();
    } catch (Exception $e) {
        // Log o ignorar error de envío
    }
}

$respuesta = "";
$deudor = buscarDeudor($telefonoConPrefijo);

if ($deudor) {
    $nombre = ucfirst(strtolower($deudor["nombre"]));
    $dni = $deudor["dni"];
    $telEjecutivo = preg_replace("/[^0-9]/", "", $deudor["tel_ejecutivo"]);
    $link = "https://wa.me/54$telEjecutivo?text=Hola+rgonzalez%2C+soy+*" . urlencode($nombre) . "*+%28DNI%3A+*" . $dni . "*%29%2C+tengo+una+consulta";
    $respuesta = "Hola $nombre, podés escribirle directamente a tu ejecutivo desde este enlace: $link";
    registrarVisita($telefonoConPrefijo);
    enviarEmail($sender, $message);
} elseif (preg_match('/\b(\d{1,2}\.?\d{3}\.?\d{3})\b|\b\d{7,9}\b/', $message, $coinc)) {
    $dni = preg_replace('/\D/', '', $coinc[0]);
    $lineas = [];
    $deudaEncontrada = null;
    if (file_exists("deudores.csv")) {
        $fp = fopen("deudores.csv", "r");
        while (($line = fgetcsv($fp, 0, ";")) !== false) {
            if (count($line) >= 5 && trim($line[1]) == $dni) {
                $line[2] = $telefonoConPrefijo;
                $deudaEncontrada = ["nombre" => $line[0], "dni" => $line[1], "ejecutivo" => $line[3], "tel_ejecutivo" => $line[4]];
            }
            $lineas[] = $line;
        }
        fclose($fp);

        if ($deudaEncontrada) {
            $fp = fopen("deudores.csv", "w");
            foreach ($lineas as $l) fputcsv($fp, $l, ";");
            fclose($fp);

            $nombre = ucfirst(strtolower($deudaEncontrada["nombre"]));
            $link = "https://wa.me/54" . preg_replace("/[^0-9]/", "", $deudaEncontrada["tel_ejecutivo"]) . "?text=Hola+rgonzalez%2C+soy+*" . urlencode($nombre) . "*+%28DNI%3A+*" . $dni . "*%29%2C+tengo+una+consulta";
            $respuesta = "Hola $nombre, podés escribirle directamente a tu ejecutivo desde este enlace: $link";
            registrarVisita($telefonoConPrefijo);
            enviarEmail($sender, $message);
        } else {
            $respuesta = "No encontramos deuda con ese DNI. ¿Podrías verificar si está bien escrito?";
        }
    }
} else {
    $respuesta = "Hola. ¿Podrías indicarnos tu DNI para identificarte?";
}

file_put_contents("historial.txt", date("Y-m-d H:i") . " | $sender => $message\n", FILE_APPEND);
echo json_encode(["reply" => $respuesta]);
exit;
