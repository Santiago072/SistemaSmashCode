<?php
/**
 * recuperar.php — Recuperación de contraseña
 */
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';
require_once '../../includes/correo.php';

iniciarSesion();
if (estaAutenticado()) {
    redirigir('index.php');
}

$error = '';
$exito = '';
$csrf = generarTokenCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Solicitud inválida. Recarga la página.';
    } else {
        $correo = limpiar($_POST['correo'] ?? '');
        if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo electrónico válido.';
        } else {
            $pdo = obtenerConexion();
            $stmt = $pdo->prepare('SELECT id, nombre_completo FROM usuarios WHERE correo = ? LIMIT 1');
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // Invalida tokens anteriores
                $pdo->prepare('UPDATE token_recuperacion SET usado = 1 WHERE usuario_id = ?')->execute([$usuario['id']]);

                // Genera nuevo token
                $token_string = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                $pdo->prepare('INSERT INTO token_recuperacion (usuario_id, token, expira_en) VALUES (?, ?, ?)')
                    ->execute([$usuario['id'], $token_string, $expira]);

                // Enlace (ajustar protocolo/host en producción)
                $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $enlace = $protocolo . $_SERVER['HTTP_HOST'] . "/proyecto_smashcode/modulos/auth/restablecer.php?token=" . $token_string;

                $cuerpo = "<h1>Recuperación de Contraseña</h1>";
                $cuerpo .= "<p>Hola " . limpiar($usuario['nombre_completo']) . ",</p>";
                $cuerpo .= "<p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace. Este enlace expira en 24 horas.</p>";
                $cuerpo .= "<p><a href='$enlace'>$enlace</a></p>";
                $cuerpo .= "<p>Si no fuiste tú, ignora este mensaje.</p>";

                enviarCorreo($correo, 'Recupera tu contraseña en SmashCode', $cuerpo);
            }
            
            // Siempre mostramos éxito para no revelar si el correo existe
            $exito = 'Si el correo está registrado, te hemos enviado las instrucciones para restablecer tu contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Contraseña — SmashCode</title>
  <link rel="stylesheet" href="../../assets/css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<main class="pagina-auth">
  <div class="contenedor-auth animar-entrada" style="max-width: 500px;">
    <div class="panel-auth-der" style="border-radius: var(--radio);">
      <h2 class="titulo-formulario" style="text-align:center;">Recuperar Contraseña</h2>
      <p class="subtitulo-formulario" style="text-align:center;">Ingresa tu correo para recibir un enlace de recuperación.</p>

      <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-circle-exclamation"></i><?= $error ?></div>
      <?php endif; ?>
      <?php if ($exito): ?>
        <div class="alerta alerta-exito"><i class="fas fa-circle-check"></i><?= $exito ?></div>
      <?php endif; ?>

      <form method="POST" action="recuperar.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="correo">Correo Electrónico</label>
          <div class="contenedor-input">
            <i class="fas fa-envelope icono-input"></i>
            <input type="email" id="correo" name="correo" class="campo-input" placeholder="tucorreo@sena.edu.co" required>
          </div>
        </div>

        <button type="submit" class="btn btn-verde" style="margin-top: 10px;">
          <i class="fas fa-paper-plane"></i> Enviar Enlace
        </button>
        
        <div style="text-align:center; margin-top: 20px;">
          <a href="login.php" style="font-size:0.85rem; color:var(--azul); font-weight:700;"><i class="fas fa-arrow-left"></i> Volver a Iniciar Sesión</a>
        </div>
      </form>
    </div>
  </div>
</main>
</body>
</html>
