<?php
/**
 * restablecer.php — Restablecer contraseña con token
 */
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
if (estaAutenticado()) {
    redirigir('index.php');
}

$error = '';
$exito = '';
$csrf = generarTokenCSRF();
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if (empty($token)) {
    redirigir('modulos/auth/login.php');
}

$pdo = obtenerConexion();
// Verificar token válido y no expirado
$stmt = $pdo->prepare('SELECT usuario_id FROM token_recuperacion WHERE token = ? AND usado = 0 AND expira_en > NOW() LIMIT 1');
$stmt->execute([$token]);
$tokenRow = $stmt->fetch();

if (!$tokenRow) {
    $error = 'El enlace de recuperación es inválido o ha expirado. Por favor, solicita uno nuevo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRow) {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Solicitud inválida. Recarga la página.';
    } else {
        $clave = $_POST['contrasena'] ?? '';
        
        if (strlen($clave) < 8 || !preg_match('/[A-Z]/', $clave) || !preg_match('/[0-9]/', $clave)) {
            $error = 'La contraseña debe tener mínimo 8 caracteres, 1 mayúscula y 1 número.';
        } else {
            $hash = password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $pdo->beginTransaction();
            try {
                // Actualizar contraseña y desbloquear cuenta si estaba bloqueada
                $pdo->prepare('UPDATE usuarios SET contrasena = ?, intentos_fallidos = 0, bloqueado = 0 WHERE id = ?')
                    ->execute([$hash, $tokenRow['usuario_id']]);
                
                // Marcar token como usado
                $pdo->prepare('UPDATE token_recuperacion SET usado = 1 WHERE token = ?')
                    ->execute([$token]);
                
                $pdo->commit();
                $exito = 'Tu contraseña ha sido actualizada con éxito.';
                $tokenRow = null; // Oculta el formulario
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Hubo un error al actualizar la contraseña.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer Contraseña — SmashCode</title>
  <link rel="stylesheet" href="../../assets/css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<main class="pagina-auth">
  <div class="contenedor-auth animar-entrada" style="max-width: 500px;">
    <div class="panel-auth-der" style="border-radius: var(--radio);">
      <h2 class="titulo-formulario" style="text-align:center;">Restablecer Contraseña</h2>
      
      <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-circle-exclamation"></i><?= $error ?></div>
      <?php endif; ?>
      <?php if ($exito): ?>
        <div class="alerta alerta-exito"><i class="fas fa-circle-check"></i><?= $exito ?></div>
        <div style="text-align:center; margin-top: 20px;">
          <a href="login.php" class="btn btn-verde"><i class="fas fa-right-to-bracket"></i> Ir a Iniciar Sesión</a>
        </div>
      <?php endif; ?>

      <?php if ($tokenRow): ?>
      <p class="subtitulo-formulario" style="text-align:center;">Ingresa tu nueva contraseña a continuación.</p>
      <form method="POST" action="restablecer.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="token" value="<?= limpiar($token) ?>">
        
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="contrasena">Nueva Contraseña</label>
          <div class="contenedor-input">
            <i class="fas fa-lock icono-input"></i>
            <input type="password" id="contrasena" name="contrasena" class="campo-input" placeholder="Mín. 8 caracteres, 1 Mayúscula, 1 número" required>
          </div>
          <span class="ayuda-campo">Incluye al menos 1 mayúscula y 1 número</span>
        </div>

        <button type="submit" class="btn btn-verde" style="margin-top: 10px;">
          <i class="fas fa-save"></i> Guardar Contraseña
        </button>
      </form>
      <?php elseif (!$exito && !$tokenRow): ?>
        <div style="text-align:center; margin-top: 20px;">
          <a href="recuperar.php" style="font-size:0.85rem; color:var(--azul); font-weight:700;"><i class="fas fa-redo"></i> Solicitar nuevo enlace</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
