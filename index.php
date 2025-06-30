<?php
ini_set('display_errors', 0);
error_reporting(0);
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

$sender = preg_replace('/\D/', '', $_POST["sender"] ?? "");
$message = trim($_POST["message"] ?? "");

if (strlen($sender) < 10) exit(json_encode(["reply" => ""]));
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
} else {
    $respuesta = "Hola. ¿Podrías indicarnos tu DNI para identificarte?";
}

echo json_encode(["reply" => $respuesta]);
exit;
