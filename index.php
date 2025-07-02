<?php 
ini_set('display_errors', 0);
error_reporting(0);
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json');

$app = $_POST["app"] ?? "";
$senderRaw = $_POST["sender"] ?? "";
$message = strtolower(trim($_POST["message"] ?? ""));

$sender = preg_replace('/\D/', '', $senderRaw);
if (strlen($sender) < 10) exit(json_encode(["reply" => ""]));
$telefonoBase = substr($sender, -10);
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
                return ["nombre" => $line[0], "dni" => $line[1], "telefono" => $telefonoCSV, "deuda" => $line[3], "ejecutivo" => $line[4]];
            }
        }
    }
    fclose($fp);
    return null;
}

function mensajeConLink($nombre, $dni, $ejecutivo) {
    $nombreLink = urlencode($nombre);
    $dniLink = urlencode($dni);
    $mensaje = "Hola $nombre, podés escribirle directamente a tu ejecutivo desde este enlace: https://wa.me/$ejecutivo?text=Hola+rgonzalez%2C+soy+%2A$nombreLink%2A+%28DNI%3A+%2A$dniLink%2A%29%2C+tengo+una+consulta";
    return $mensaje;
}

$respuesta = "";
$deudor = buscarDeudor($telefonoConPrefijo);

if (contiene($message, ["equivocado", "número equivocado", "numero equivocado"])) {
    registrarVisita($telefonoConPrefijo);
    echo json_encode(["reply" => "Entendido. Eliminamos tu número de nuestra base de gestión."]);
    exit;
} elseif (contiene($message, ["gracia", "gracias", "graciah"])) {
    $respuesta = "Gracias a vos por comunicarte. Estamos para ayudarte.";
} elseif ($deudor) {
    $nombre = ucfirst(strtolower($deudor["nombre"]));
    $dni = $deudor["dni"];
    $ejecutivo = preg_replace('/\D/', '', $deudor["ejecutivo"]);
    $respuesta = mensajeConLink($nombre, $dni, $ejecutivo);
    registrarVisita($telefonoConPrefijo);
} elseif (preg_match('/\b(\d{1,2}\.\d{3}\.\d{3}|\d{7,9})\b/', $message, $coinc)) {
    $dni = preg_replace('/\D/', '', $coinc[0]);
    $deudaEncontrada = null;
    $lineas = [];
    if (file_exists("deudores.csv")) {
        $fp = fopen("deudores.csv", "r");
        while (($line = fgetcsv($fp, 0, ";")) !== false) {
            if (count($line) >= 5) {
                if (trim($line[1]) == $dni) {
                    $line[2] = $telefonoConPrefijo; // Actualiza teléfono
                    $deudaEncontrada = ["nombre" => $line[0], "dni" => $line[1], "ejecutivo" => preg_replace('/\D/', '', $line[4])];
                }
                $lineas[] = $line;
            }
        }
        fclose($fp);
    }
    if ($deudaEncontrada) {
        $fp = fopen("deudores.csv", "w");
        foreach ($lineas as $l) fputcsv($fp, $l, ";");
        fclose($fp);
        $nombre = ucfirst(strtolower($deudaEncontrada["nombre"]));
        $ejecutivo = $deudaEncontrada["ejecutivo"];
        $respuesta = mensajeConLink($nombre, $dni, $ejecutivo);
        registrarVisita($telefonoConPrefijo);
    } else {
        $respuesta = "No encontramos deuda con ese DNI. ¿Podrías verificar si está bien escrito?";
    }
} else {
    $respuesta = "Hola. ¿Podrías indicarnos tu DNI para identificarte?";
}

file_put_contents("historial.txt", date("Y-m-d H:i") . " | $sender => $message\n", FILE_APPEND);
echo json_encode(["reply" => $respuesta]);
exit;
?>
