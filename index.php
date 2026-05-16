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
    /* Permitir solo aprendices — admin e instructor tienen su propio dashboard */
    if (!in_array(obtenerRolSesion(), ['aprendiz'], true)) {
        $rol = obtenerRolSesion();
        if ($rol === 'admin')       redirigir('modulos/admin/dashboard.php');
        elseif ($rol === 'instructor') redirigir('modulos/instructor/dashboard.php');
    }

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
    /* Panel aprendiz — tema claro Duolingo */
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
    /* ── Línea del mapa ── */
    .linea-mapa {
      width: 4px; height: 32px;
      background: var(--gris-claro);
      margin: 0 auto; border-radius: 2px;
    }
    /* ── Grupo de cada nodo (burbuja + pingüino lateral) ── */
    .grupo-rap {
      display: flex; flex-direction: column;
      align-items: center; position: relative;
      margin-bottom: 4px;
    }
    /* Pingüino al costado del nodo activo */
    .pinguino-mapa {
      position: absolute;
      right: -100px; bottom: 0;
      width: 72px;
      animation: flotar 2.5s ease-in-out infinite;
      pointer-events: none;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15));
    }
    /* Burbuja circular del nodo */
    .burbuja-rap {
      width: 72px; height: 72px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.7rem; font-weight: 900;
      cursor: pointer; transition: transform 0.15s;
      border: 4px solid transparent; position: relative;
    }
    .burbuja-rap:hover { transform: scale(1.08); }
    .burbuja-rap.completado {
      background: var(--verde); border-color: var(--verde-oscuro);
      color: #fff; box-shadow: 0 4px 0 var(--verde-oscuro);
    }
    .burbuja-rap.disponible, .burbuja-rap.en_progreso {
      background: var(--amarillo); border-color: #D4A800;
      color: #fff; box-shadow: 0 4px 0 #D4A800;
      animation: pulsar-duo 2s infinite;
    }
    .burbuja-rap.bloqueado {
      background: var(--gris-claro); border-color: #C0C0C0;
      color: var(--gris-medio); cursor: not-allowed;
      box-shadow: 0 4px 0 #C0C0C0;
    }
    .etiqueta-burbuja {
      font-size: 0.7rem; font-weight: 800;
      color: var(--gris-medio); margin-top: 6px;
      text-transform: uppercase; letter-spacing: 0.06em;
    }
    .burbuja-rap.disponible ~ .etiqueta-burbuja,
    .burbuja-rap.en_progreso ~ .etiqueta-burbuja { color: var(--verde-oscuro); }
    /* Panel derecho */
    .panel-lateral-derecho {
      width: 320px; flex-shrink: 0;
      padding: 28px 20px; display: flex;
      flex-direction: column; gap: 14px;
      border-left: 2px solid var(--borde);
    }
    .tarjeta-liga {
      background: var(--blanco); border: 2px solid var(--borde);
      border-radius: var(--radio); padding: 16px;
    }
    .tarjeta-liga .titulo-tarjeta {
      font-size: 0.78rem; font-weight: 800;
      text-transform: uppercase; letter-spacing: 0.06em;
      color: var(--gris-medio); margin-bottom: 8px;
      display: flex; align-items: center; gap: 8px;
    }
    .tarjeta-liga .titulo-tarjeta a { margin-left:auto; color:var(--azul); font-size:0.7rem; }
    .tarjeta-liga p { font-size: 0.82rem; color: var(--gris-medio); }
    .desafio-item {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 0; border-bottom: 2px solid var(--borde);
    }
    .desafio-item:last-child { border-bottom: none; }
    .desafio-texto { flex:1; font-size:0.82rem; font-weight:600; color:var(--gris-texto); }
    .mini-barra { height:8px; background:var(--gris-claro); border-radius:var(--radio-full); overflow:hidden; margin-top:4px; }
    .mini-barra .relleno { height:100%; background:var(--naranja); border-radius:var(--radio-full); }
    .mini-texto { font-size:0.65rem; color:var(--gris-medio); margin-top:2px; }
    .caja-acceso {
      background: var(--blanco); border: 2px solid var(--borde);
      border-radius: var(--radio); padding: 20px;
      display: flex; flex-direction: column; gap: 10px; text-align:center;
    }
    .caja-acceso p { font-size:0.85rem; color:var(--gris-texto); font-weight:600; margin-bottom:4px; }
  </style>
</head>
<body>

<div class="contenedor-app">

  <!-- ============ BARRA LATERAL ============ -->
  <nav class="barra-lateral" aria-label="Navegación principal">
    <div class="logo-app">
      <div class="logo-icono">🐧</div>
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
      <li>
        <a href="modulos/auth/cerrar-sesion.php" class="nav-enlace" style="color:var(--rojo);">
          <i class="fas fa-right-from-bracket nav-icono"></i>
          <span>Cerrar Sesión</span>
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
        $primerActivo = true; // Controla en cuál nodo aparece el pingüino
        foreach ($niveles as $nivel):
          $estado = $autenticado
              ? estadoRap($nivel, $mapaProgreso, $niveles)
              : ($nivel['orden'] === 1 ? 'disponible' : 'bloqueado');

          $iconos = ['🏥','📋','❤️','💊','🚨','⭐'];
          $icono  = $iconos[$nivel['orden'] - 1] ?? '📘';

          /* El pingüino aparece solo en el primer nodo disponible/en progreso */
          $esPrincipal = ($estado === 'disponible' || $estado === 'en_progreso') && $primerActivo;
          if ($esPrincipal) $primerActivo = false;

          /* Ícono dentro de la burbuja según estado */
          $iconoBurbuja = match($estado) {
            'completado'  => '<i class="fas fa-check" style="font-size:1.6rem;"></i>',
            'en_progreso',
            'disponible'  => '<i class="fas fa-star"  style="font-size:1.6rem;"></i>',
            default       => '<i class="fas fa-lock"  style="font-size:1.4rem;"></i>',
          };

          $urlRap = $autenticado && $estado !== 'bloqueado'
              ? 'modulos/aprendiz/rap.php?id=' . urlencode($nivel['rap_id'])
              : '#';
        ?>

        <!-- Cabecera de sección (barra verde estilo Duolingo) -->
        <div class="seccion-nivel-card">
          <span style="font-size:1.2rem;"><?= $icono ?></span>
          <div>
            <div style="font-size:0.7rem;opacity:0.85;">ETAPA 1, SECCIÓN <?= $nivel['orden'] ?></div>
            <div style="font-size:0.95rem;"><?= limpiar($nivel['nombre']) ?></div>
          </div>
          <?php if ($estado !== 'bloqueado'): ?>
          <button class="btn-guia" style="margin-left:auto;">
            <i class="fas fa-book-open"></i> GUÍA
          </button>
          <?php endif; ?>
        </div>

        <!-- Línea + nodo con pingüino al costado si es el activo -->
        <div class="linea-mapa"></div>
        <div class="grupo-rap">
          <div class="burbuja-rap <?= $estado ?>"
               onclick="<?= $estado !== 'bloqueado' ? "window.location='{$urlRap}'" : "mostrarMensajeBloqueado()" ?>"
               title="<?= limpiar($nivel['rap_titulo']) ?>"
               role="button"
               tabindex="<?= $estado === 'bloqueado' ? '-1' : '0' ?>">
            <?= $iconoBurbuja ?>
          </div>

          <?php if ($esPrincipal): ?>
          <!-- Pingüino mascota aparece junto al nodo activo -->
          <svg class="pinguino-mapa" viewBox="0 0 110 130" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="55" cy="78" rx="38" ry="44" fill="#1A1A2E"/>
            <ellipse cx="55" cy="84" rx="24" ry="30" fill="#FFFFFF"/>
            <ellipse cx="55" cy="38" rx="28" ry="28" fill="#1A1A2E"/>
            <ellipse cx="55" cy="42" rx="18" ry="18" fill="#FFFFFF"/>
            <circle cx="47" cy="36" r="6" fill="#FFFFFF"/><circle cx="47" cy="36" r="3.5" fill="#1A1A2E"/><circle cx="48.5" cy="34.5" r="1.2" fill="#FFFFFF"/>
            <circle cx="63" cy="36" r="6" fill="#FFFFFF"/><circle cx="63" cy="36" r="3.5" fill="#1A1A2E"/><circle cx="64.5" cy="34.5" r="1.2" fill="#FFFFFF"/>
            <ellipse cx="55" cy="48" rx="6" ry="4" fill="#FF9600"/>
            <ellipse cx="20" cy="75" rx="10" ry="22" fill="#1A1A2E" transform="rotate(-15 20 75)"/>
            <ellipse cx="90" cy="75" rx="10" ry="22" fill="#1A1A2E" transform="rotate(15 90 75)"/>
            <ellipse cx="43" cy="120" rx="12" ry="6" fill="#FF9600"/>
            <ellipse cx="67" cy="120" rx="12" ry="6" fill="#FF9600"/>
            <rect x="34" y="14" width="42" height="10" rx="5" fill="#FFFFFF"/>
            <rect x="51" y="8" width="8" height="20" rx="4" fill="#FF4B4B"/>
            <rect x="34" y="16" width="42" height="4" rx="2" fill="#E5E5E5"/>
          </svg>
          <?php endif; ?>

          <?php if ($esPrincipal): ?>
            <span class="etiqueta-burbuja" style="color:var(--verde-oscuro);">EMPEZAR</span>
          <?php elseif ($estado === 'completado'): ?>
            <span class="etiqueta-burbuja" style="color:var(--verde);">COMPLETADO</span>
          <?php elseif ($estado === 'bloqueado'): ?>
            <span class="etiqueta-burbuja">BLOQUEADO</span>
          <?php endif; ?>
        </div>
        <div class="linea-mapa"></div>

        <?php endforeach; ?>
      </section>

      <!-- PANEL LATERAL DERECHO -->
      <aside class="panel-lateral-derecho" aria-label="Panel de gamificación">

        <!-- ¡Compite en las ligas! -->
        <div class="tarjeta-liga">
          <div class="titulo-tarjeta">🏆 ¡Compite en las Ligas!</div>
          <p>Completa lecciones para empezar a competir</p>
        </div>

        <!-- Desafíos del día -->
        <div class="tarjeta-liga">
          <div class="titulo-tarjeta">
            ⚡ Desafíos del día
            <a href="#">VER TODOS</a>
          </div>
          <div class="desafio-item">
            <span style="font-size:1.3rem; color:var(--naranja);">⚡</span>
            <div class="desafio-texto">
              Gana 10 XP
              <div class="mini-barra">
                <div class="relleno" style="width:<?= $autenticado ? min(100,($usuario['xp_puntos']??0)/10*100) : 0 ?>%"></div>
              </div>
              <div class="mini-texto"><?= $autenticado ? min(10,$usuario['xp_puntos']??0) : 0 ?> / 10</div>
            </div>
            <span style="font-size:1.4rem;">🎁</span>
          </div>
        </div>

        <!-- CTA para visitantes / insignias para autenticados -->
        <?php if (!$autenticado): ?>
        <div class="caja-acceso">
          <p>¡Crea un perfil para guardar tu progreso!</p>
          <a href="modulos/auth/login.php?accion=registrar" class="btn btn-verde">
            <i class="fas fa-user-plus"></i> CREAR PERFIL
          </a>
          <a href="modulos/auth/login.php" class="btn btn-blanco">INGRESAR</a>
        </div>
        <?php else: ?>
        <div class="tarjeta-liga">
          <div class="titulo-tarjeta">🎖 Mis Insignias</div>
          <p>Completa RAPs y quizzes para ganar insignias.</p>
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