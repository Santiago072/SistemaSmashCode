<?php
/**
 * index.php — Panel principal del Aprendiz (módulo de aprendizaje)
 * Muestra el mapa de niveles y progreso gamificado (HU01, HU05, RF08, RF09).
 */

require_once 'config/conexion.php';
require_once 'config/sesion.php';
require_once 'includes/funciones.php';

iniciarSesion();

/* El panel es público para visitantes; muestra botones de acceso */
$autenticado = estaAutenticado();
$usuario     = null;
$progreso    = [];

if ($autenticado) {
    requerirRol('aprendiz'); // Sólo aprendices acceden aquí

    $pdo  = obtenerConexion();
    $uid  = $_SESSION['usuario_id'];

    /* Datos del usuario */
    $stmt = $pdo->prepare('SELECT nombre_completo, xp_puntos, nivel_perfil FROM usuarios WHERE id = ?');
    $stmt->execute([$uid]);
    $usuario = $stmt->fetch();

    /* Progreso por RAP */
    $stmt = $pdo->prepare(
        'SELECT p.rap_id, p.porcentaje, p.completado, r.titulo, r.nivel_id, n.nombre AS nombre_nivel, n.orden AS orden_nivel
         FROM progreso p
         JOIN rap r ON r.id = p.rap_id
         JOIN nivel n ON n.id = r.nivel_id
         WHERE p.usuario_id = ?
         ORDER BY n.orden'
    );
    $stmt->execute([$uid]);
    $progreso = $stmt->fetchAll();
}

/* Obtener todos los niveles y sus RAPs */
$pdo    = obtenerConexion();
$niveles = $pdo->query(
    'SELECT n.id, n.nombre, n.descripcion, n.orden, n.activo, n.umbral_desbloqueo,
            r.id AS rap_id, r.titulo AS rap_titulo
     FROM nivel n
     LEFT JOIN rap r ON r.nivel_id = n.id AND r.activo = 1
     WHERE n.activo = 1
     ORDER BY n.orden'
)->fetchAll();

/* Indexar progreso por rap_id para consulta rápida */
$mapaProgreso = [];
foreach ($progreso as $p) {
    $mapaProgreso[$p['rap_id']] = $p;
}

/**
 * Determina el estado de un RAP para el aprendiz.
 * @param array $nivel  Datos del nivel
 * @param array $map    Mapa de progreso
 * @param array $todos  Todos los niveles
 */
function estadoRap(array $nivel, array $map, array $todos): string {
    if (!isset($map[$nivel['rap_id']])) {
        // Nivel 1 siempre disponible
        if ($nivel['orden'] === 1) return 'disponible';
        // Verificar si el nivel anterior está completado al 80%+
        $anterior = array_filter($todos, fn($n) => $n['orden'] === $nivel['orden'] - 1);
        $anterior = reset($anterior);
        if ($anterior && isset($map[$anterior['rap_id']])) {
            return $map[$anterior['rap_id']]['porcentaje'] >= 80 ? 'disponible' : 'bloqueado';
        }
        return $nivel['orden'] === 1 ? 'disponible' : 'bloqueado';
    }
    $p = $map[$nivel['rap_id']];
    if ($p['completado']) return 'completado';
    if ($p['porcentaje'] > 0) return 'en_progreso';
    return 'disponible';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aprender — SmashCode Enfermería SENA</title>
  <meta name="description" content="Aprende inglés clínico con SmashCode, plataforma gamificada para enfermería SENA.">
  <link rel="stylesheet" href="assets/css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* Estilos específicos del panel de aprendiz */
    .contenedor-aprendiz {
      display: flex;
      gap: 0;
      min-height: 100vh;
    }
    .zona-mapa {
      flex: 1;
      padding: 28px;
      overflow-y: auto;
    }
    .nodo-nivel-card {
      background: var(--fondo-tarjeta);
      border: 1px solid var(--borde-sutil);
      border-radius: var(--radio-md);
      padding: 14px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      cursor: pointer;
      transition: var(--transicion);
      margin-bottom: 8px;
      max-width: 500px;
      margin-left: auto;
      margin-right: auto;
    }
    .nodo-nivel-card:hover {
      border-color: var(--verde-acento);
      background: var(--fondo-hover);
    }
    .nodo-nivel-card .insignia-nivel {
      font-size: 1.4rem;
      width: 42px;
      text-align: center;
    }
    .nodo-nivel-card .info-nivel .nombre-nivel {
      font-size: var(--texto-sm);
      font-weight: 600;
      color: var(--texto-principal);
    }
    .nodo-nivel-card .info-nivel .sub-nivel {
      font-size: var(--texto-xs);
      color: var(--texto-secundario);
    }
    .nodo-nivel-card .btn-guia {
      margin-left: auto;
      background: rgba(30,132,73,0.15);
      border: 1px solid var(--borde-activo);
      color: var(--verde-acento);
      padding: 5px 12px;
      border-radius: var(--radio-full);
      font-size: var(--texto-xs);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transicion);
    }
    .nodo-nivel-card .btn-guia:hover {
      background: var(--verde-salud);
      color: #fff;
    }
    /* Línea conectora del mapa */
    .linea-mapa {
      width: 3px;
      height: 30px;
      background: linear-gradient(to bottom, var(--verde-salud), var(--azul-institucional));
      margin: 0 auto;
      border-radius: 2px;
    }
    .grupo-rap {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 6px;
    }
    /* Círculo del RAP */
    .burbuja-rap {
      width: 68px;
      height: 68px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      border: 3px solid transparent;
      transition: var(--transicion);
      cursor: pointer;
      position: relative;
    }
    .burbuja-rap.completado {
      background: var(--verde-salud);
      border-color: var(--verde-acento);
      color: #fff;
      box-shadow: 0 0 22px rgba(46,204,113,0.5);
    }
    .burbuja-rap.en_progreso {
      background: linear-gradient(135deg, var(--azul-institucional), var(--azul-claro));
      border-color: var(--azul-claro);
      color: #fff;
      animation: pulsar 2s infinite;
    }
    .burbuja-rap.disponible {
      background: linear-gradient(135deg, var(--azul-institucional), var(--azul-claro));
      border-color: var(--azul-claro);
      color: #fff;
    }
    .burbuja-rap.bloqueado {
      background: var(--fondo-input);
      border-color: var(--texto-tenue);
      color: var(--texto-tenue);
      cursor: not-allowed;
    }
    .etiqueta-burbuja {
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--texto-secundario);
      margin-top: 6px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    /* Tooltip del mascot */
    .mensaje-mascota {
      position: absolute;
      top: -60px;
      right: -130px;
      background: var(--fondo-panel);
      border: 1px solid var(--borde-sutil);
      border-radius: var(--radio-md) var(--radio-md) var(--radio-md) 0;
      padding: 8px 12px;
      font-size: var(--texto-xs);
      color: var(--texto-principal);
      white-space: nowrap;
      pointer-events: none;
    }
    /* Panel de la derecha */
    .panel-lateral-derecho {
      width: 310px;
      padding: 28px 24px 28px 12px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      border-left: 1px solid var(--borde-sutil);
      background: var(--fondo-panel);
    }
    .tarjeta-liga {
      background: var(--fondo-tarjeta);
      border: 1px solid var(--borde-sutil);
      border-radius: var(--radio-md);
      padding: 16px;
    }
    .tarjeta-liga .titulo-tarjeta {
      font-size: var(--texto-sm);
      font-weight: 600;
      color: var(--acento-oro);
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tarjeta-liga p {
      font-size: var(--texto-xs);
      color: var(--texto-secundario);
    }
    .desafio-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--borde-sutil);
    }
    .desafio-item:last-child { border-bottom: none; }
    .desafio-item .icono-desafio {
      font-size: 1.1rem;
      color: var(--acento-oro);
    }
    .desafio-item .texto-desafio {
      flex: 1;
      font-size: var(--texto-xs);
      color: var(--texto-principal);
    }
    .mini-progreso {
      height: 6px;
      background: var(--fondo-input);
      border-radius: var(--radio-full);
      overflow: hidden;
      margin-top: 4px;
    }
    .mini-progreso .relleno {
      height: 100%;
      background: var(--verde-acento);
      border-radius: var(--radio-full);
    }
    /* Botones para visitantes */
    .caja-acceso {
      background: var(--fondo-tarjeta);
      border: 1px solid var(--borde-sutil);
      border-radius: var(--radio-md);
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .caja-acceso p {
      font-size: var(--texto-sm);
      color: var(--texto-secundario);
      margin-bottom: 6px;
    }
  </style>
</head>
<body>

<div class="contenedor-app">

  <!-- ============ BARRA LATERAL ============ -->
  <nav class="barra-lateral" aria-label="Navegación principal">
    <div class="logo-app">
      <div class="logo-icono">🏥</div>
      <span class="logo-nombre">Smash<span>Code</span></span>
    </div>

    <ul class="nav-lateral">
      <li>
        <a href="index.php" class="nav-enlace activo" aria-current="page">
          <i class="fas fa-book-open nav-icono"></i>
          <span>Aprender</span>
        </a>
      </li>
      <li>
        <a href="modulos/aprendiz/vocabulario.php" class="nav-enlace">
          <i class="fas fa-spell-check nav-icono"></i>
          <span>Vocabulario</span>
        </a>
      </li>
      <li>
        <a href="modulos/aprendiz/dialogos.php" class="nav-enlace">
          <i class="fas fa-comments nav-icono"></i>
          <span>Diálogos</span>
        </a>
      </li>
      <li>
        <a href="modulos/aprendiz/ejercicios.php" class="nav-enlace">
          <i class="fas fa-dumbbell nav-icono"></i>
          <span>Ejercicios</span>
        </a>
      </li>
      <li>
        <a href="modulos/aprendiz/glosario.php" class="nav-enlace">
          <i class="fas fa-book-medical nav-icono"></i>
          <span>Glosario</span>
        </a>
      </li>
      <?php if ($autenticado): ?>
      <li>
        <a href="modulos/aprendiz/perfil.php" class="nav-enlace">
          <i class="fas fa-user nav-icono"></i>
          <span>Perfil</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>

  <!-- ============ CONTENIDO PRINCIPAL ============ -->
  <main class="contenido-principal">

    <!-- Barra superior -->
    <?php if ($autenticado && $usuario): ?>
    <header class="barra-superior">
      <div class="stat-xp">
        <i class="fas fa-bolt"></i>
        <?= formatearXP($usuario['xp_puntos']) ?> XP
      </div>
      <div class="stat-racha">
        <i class="fas fa-fire"></i>
        Racha: 0 días
      </div>
      <div class="avatar-usuario" title="<?= limpiar($usuario['nombre_completo']) ?>">
        <?= strtoupper(substr($usuario['nombre_completo'], 0, 1)) ?>
      </div>
    </header>
    <?php endif; ?>

    <!-- Zona del mapa + panel derecho -->
    <div class="contenedor-aprendiz">

      <!-- MAPA DE PROGRESO -->
      <section class="zona-mapa">
        <?php
        $ultimoEnProgreso = true;
        foreach ($niveles as $nivel):
          $estado = $autenticado
              ? estadoRap($nivel, $mapaProgreso, $niveles)
              : ($nivel['orden'] === 1 ? 'disponible' : 'bloqueado');

          $iconos = ['🏥','📋','❤️','💊','🚨','⭐'];
          $icono  = $iconos[$nivel['orden'] - 1] ?? '📘';

          /* Porcentaje de progreso si aplica */
          $porcentaje = $mapaProgreso[$nivel['rap_id']]['porcentaje'] ?? 0;

          /* Emoji del estado */
          $emojiEstado = match($estado) {
            'completado'  => '✓',
            'en_progreso' => '★',
            'disponible'  => '★',
            default       => '🔒',
          };

          $mostrarEmpezar = ($estado === 'disponible' || $estado === 'en_progreso') && $ultimoEnProgreso;
          if ($estado === 'completado') $ultimoEnProgreso = true;
          elseif ($mostrarEmpezar) $ultimoEnProgreso = false;
        ?>

        <!-- Cabecera del nivel -->
        <div class="nodo-nivel-card">
          <span class="insignia-nivel"><?= $icono ?></span>
          <div class="info-nivel">
            <div class="nombre-nivel">ETAPA 1, SECCIÓN <?= $nivel['orden'] ?></div>
            <div class="sub-nivel"><?= limpiar($nivel['nombre']) ?></div>
          </div>
          <?php if ($estado !== 'bloqueado'): ?>
          <button class="btn-guia">
            <i class="fas fa-book-open"></i> GUÍA
          </button>
          <?php endif; ?>
        </div>

        <!-- Línea + Burbuja del RAP -->
        <div class="linea-mapa"></div>
        <div class="grupo-rap">
          <?php
          $urlRap = $autenticado && $estado !== 'bloqueado'
              ? "modulos/aprendiz/rap.php?id=" . urlencode($nivel['rap_id'])
              : "#";
          ?>
          <div class="burbuja-rap <?= $estado ?>"
               onclick="<?= $estado !== 'bloqueado' ? "window.location='{$urlRap}'" : "mostrarMensajeBloqueado()" ?>"
               title="<?= limpiar($nivel['rap_titulo']) ?>"
               role="button"
               tabindex="<?= $estado === 'bloqueado' ? '-1' : '0' ?>">
            <?= $emojiEstado ?>
            <?php if ($mostrarEmpezar): ?>
              <span class="mensaje-mascota">¡Tú puedes, enfermero/a! 💪</span>
            <?php endif; ?>
          </div>
          <?php if ($estado === 'en_progreso' || $mostrarEmpezar): ?>
            <span class="etiqueta-burbuja">EMPEZAR</span>
          <?php elseif ($estado === 'completado'): ?>
            <span class="etiqueta-burbuja" style="color: var(--verde-acento);">COMPLETADO</span>
          <?php elseif ($estado === 'bloqueado'): ?>
            <span class="etiqueta-burbuja">BLOQUEADO</span>
          <?php endif; ?>
        </div>
        <div class="linea-mapa"></div>

        <?php endforeach; ?>
      </section>

      <!-- PANEL LATERAL DERECHO -->
      <aside class="panel-lateral-derecho" aria-label="Panel de gamificación">

        <!-- Liga -->
        <div class="tarjeta-liga">
          <div class="titulo-tarjeta">🏆 ¡Compite en las Ligas!</div>
          <p>Completa lecciones para empezar a competir</p>
        </div>

        <!-- Desafíos del día -->
        <div class="tarjeta-liga">
          <div class="titulo-tarjeta">
            ⚡ Desafíos del día
            <a href="#" style="margin-left:auto; font-size:0.7rem; color: var(--azul-claro);">VER TODOS</a>
          </div>
          <div class="desafio-item">
            <span class="icono-desafio">⚡</span>
            <div class="texto-desafio">
              Gana 10 XP
              <div class="mini-progreso">
                <div class="relleno" style="width: <?= $autenticado ? min(100, ($usuario['xp_puntos'] ?? 0) / 10 * 100) : 0 ?>%"></div>
              </div>
              <span style="font-size:0.65rem; color: var(--texto-tenue);">
                <?= $autenticado ? min(10, $usuario['xp_puntos'] ?? 0) : 0 ?> / 10
              </span>
            </div>
          </div>
        </div>

        <!-- Acceso para visitantes -->
        <?php if (!$autenticado): ?>
        <div class="caja-acceso">
          <p>¡Crea un perfil para guardar tu progreso!</p>
          <a href="modulos/auth/login.php?accion=registrar" class="btn btn-primario">
            <i class="fas fa-user-plus"></i> CREAR PERFIL
          </a>
          <a href="modulos/auth/login.php" class="btn btn-secundario" style="background:#f0f0f0; color:#1A5276; border:1.5px solid #ccc;">
            INGRESAR
          </a>
        </div>
        <?php else: ?>
        <!-- Insignias recientes -->
        <div class="tarjeta-liga">
          <div class="titulo-tarjeta">🎖 Mis Insignias</div>
          <p style="color: var(--texto-tenue); font-size: 0.75rem;">
            Completa RAPs y quizzes para ganar insignias.
          </p>
        </div>
        <?php endif; ?>

      </aside>
    </div><!-- /contenedor-aprendiz -->
  </main>
</div><!-- /contenedor-app -->

<script>
  /* Mostrar mensaje cuando el aprendiz intenta acceder a un nivel bloqueado */
  function mostrarMensajeBloqueado() {
    alert('🔒 Este nivel está bloqueado. Completa el nivel anterior con al menos 80% de progreso.');
  }
</script>
</body>
</html>