<?php
/**
 * correo.php — Funciones para envío de correos usando PHPMailer
 */
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCorreo(string $destinatario, string $asunto, string $cuerpo): bool {
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor (ajustar en producción)
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io'; // Usando Mailtrap para pruebas seguras
        $mail->SMTPAuth   = true;
        $mail->Username   = 'test'; // Reemplazar con credenciales reales
        $mail->Password   = 'test'; // Reemplazar con credenciales reales
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 2525;

        // Remitente y destinatario
        $mail->setFrom('no-reply@smashcode.edu.co', 'SmashCode SENA');
        $mail->addAddress($destinatario);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;
        $mail->AltBody = strip_tags($cuerpo);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("No se pudo enviar el correo a $destinatario. Error: {$mail->ErrorInfo}");
        return false;
    }
}
