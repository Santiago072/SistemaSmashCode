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
        // Cargar configuración de correo
        if (!defined('SMTP_HOST')) {
            if (file_exists(__DIR__ . '/../config/credenciales.php')) {
                require_once __DIR__ . '/../config/credenciales.php';
            } else {
                require_once __DIR__ . '/../config/credenciales.example.php';
            }
        }

        // Configuración para Gmail SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER; 
        $mail->Password   = SMTP_PASS; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

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
