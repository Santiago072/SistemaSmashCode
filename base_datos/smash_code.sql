-- =============================================================
-- Smash Code - Script de base de datos
-- Base de datos: smash_code
-- Motor: MySQL 8.0+
-- Codificación: UTF-8
-- =============================================================

CREATE DATABASE IF NOT EXISTS smash_code
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE smash_code;

-- =============================================================
-- TABLAS DE CATÁLOGO (sin dependencias)
-- =============================================================

-- Programas de formación (ej: Técnico en Enfermería SENA)
CREATE TABLE IF NOT EXISTS programa_formacion (
    id          VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    nombre      VARCHAR(255) NOT NULL,
    descripcion VARCHAR(500) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorías gramaticales del vocabulario (ej: verbo, sustantivo)
CREATE TABLE IF NOT EXISTS categoria_vocabulario (
    id     VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    nombre VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Áreas clínicas (ej: Cardiología, Farmacología)
CREATE TABLE IF NOT EXISTS area_clinica (
    id     VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    nombre VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- USUARIOS
-- =============================================================

-- Tabla principal de usuarios (aprendices, instructores, admins)
CREATE TABLE IF NOT EXISTS usuarios (
    id               VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    nombre_completo  VARCHAR(255) NOT NULL,
    correo           VARCHAR(255) NOT NULL UNIQUE,
    contrasena       VARCHAR(255) NOT NULL,              -- hash bcrypt
    ficha_sena       VARCHAR(50)  NULL,                  -- número de ficha del aprendiz
    programa_id      VARCHAR(36)  NULL,
    rol              ENUM('aprendiz','instructor','admin') NOT NULL DEFAULT 'aprendiz',
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    correo_verificado TINYINT(1)  NOT NULL DEFAULT 0,
    xp_puntos        INT          NOT NULL DEFAULT 0,
    nivel_perfil     VARCHAR(100) NOT NULL DEFAULT 'Novato',
    intentos_fallidos INT         NOT NULL DEFAULT 0,    -- para bloqueo tras 5 intentos
    bloqueado        TINYINT(1)   NOT NULL DEFAULT 0,
    debe_cambiar_clave TINYINT(1) NOT NULL DEFAULT 0,   -- instructores creados por admin
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_usuarios_programa FOREIGN KEY (programa_id)
        REFERENCES programa_formacion(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens para recuperación de contraseña (RF06)
CREATE TABLE IF NOT EXISTS token_recuperacion (
    id         VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    usuario_id VARCHAR(36)  NOT NULL,
    token      VARCHAR(255) NOT NULL UNIQUE,
    expira_en  DATETIME     NOT NULL,
    usado      TINYINT(1)   NOT NULL DEFAULT 0,          -- un solo uso
    PRIMARY KEY (id),
    CONSTRAINT fk_token_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- GAMIFICACIÓN
-- =============================================================

-- Insignias disponibles en el sistema
CREATE TABLE IF NOT EXISTS insignia (
    id          VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    nombre      VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) NULL,
    icono_url   VARCHAR(255) NULL,
    criterio    VARCHAR(255) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insignias ganadas por cada usuario
CREATE TABLE IF NOT EXISTS insignia_usuario (
    usuario_id  VARCHAR(36) NOT NULL,
    insignia_id VARCHAR(36) NOT NULL,
    ganada_en   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, insignia_id),
    CONSTRAINT fk_insig_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_insig_insignia FOREIGN KEY (insignia_id) REFERENCES insignia(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Puntuación semanal para el leaderboard (RF35, HU15)
CREATE TABLE IF NOT EXISTS puntaje_semanal (
    id              VARCHAR(36) NOT NULL DEFAULT (UUID()),
    usuario_id      VARCHAR(36) NOT NULL,
    numero_semana   INT         NOT NULL,
    anio            INT         NOT NULL,
    puntaje_total   INT         NOT NULL DEFAULT 0,
    posicion_rank   INT         NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_puntaje_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- ESTRUCTURA CURRICULAR: NIVELES Y RAPs
-- =============================================================

-- 6 niveles fijos alineados al MCER (A1→B2) — no se crean ni eliminan en operación
CREATE TABLE IF NOT EXISTS nivel (
    id                 VARCHAR(36)    NOT NULL DEFAULT (UUID()),
    nombre             VARCHAR(255)   NOT NULL,
    descripcion        VARCHAR(500)   NULL,
    imagen_url         VARCHAR(255)   NULL,
    orden              INT            NOT NULL,           -- 1=A1 … 6=B2
    activo             TINYINT(1)     NOT NULL DEFAULT 1,
    umbral_desbloqueo  DECIMAL(10,2)  NOT NULL DEFAULT 80.00, -- % mínimo del nivel anterior
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RAPs: un resultado de aprendizaje por nivel
CREATE TABLE IF NOT EXISTS rap (
    id     VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    nivel_id VARCHAR(36) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    orden  INT          NOT NULL DEFAULT 1,
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_rap_nivel FOREIGN KEY (nivel_id) REFERENCES nivel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progreso del aprendiz por RAP (RF29, RF30, HU05)
CREATE TABLE IF NOT EXISTS progreso (
    id                VARCHAR(36)   NOT NULL DEFAULT (UUID()),
    usuario_id        VARCHAR(36)   NOT NULL,
    rap_id            VARCHAR(36)   NOT NULL,
    porcentaje        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    completado        TINYINT(1)    NOT NULL DEFAULT 0,
    ultimo_acceso     DATETIME      NULL,
    tiempo_total_seg  INT           NOT NULL DEFAULT 0,
    mejor_puntaje_quiz DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    UNIQUE KEY uk_progreso (usuario_id, rap_id),
    CONSTRAINT fk_prog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE CASCADE,
    CONSTRAINT fk_prog_rap     FOREIGN KEY (rap_id)     REFERENCES rap(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- VOCABULARIO
-- =============================================================

-- Vocabulario médico bilingüe con audio e IPA (RF16, RF17)
CREATE TABLE IF NOT EXISTS vocabulario (
    id               VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    rap_id           VARCHAR(36)  NOT NULL,
    termino_en       VARCHAR(255) NOT NULL,
    termino_es       VARCHAR(255) NOT NULL,
    categoria_id     VARCHAR(36)  NULL,
    area_clinica_id  VARCHAR(36)  NULL,
    transcripcion_ipa VARCHAR(255) NULL,
    audio_url        VARCHAR(255) NULL,
    imagen_url       VARCHAR(255) NULL,
    oracion_ejemplo  VARCHAR(500) NULL,
    nivel_dificultad VARCHAR(50)  NULL,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_vocab_rap       FOREIGN KEY (rap_id)          REFERENCES rap(id),
    CONSTRAINT fk_vocab_categoria FOREIGN KEY (categoria_id)    REFERENCES categoria_vocabulario(id) ON DELETE SET NULL,
    CONSTRAINT fk_vocab_area      FOREIGN KEY (area_clinica_id) REFERENCES area_clinica(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Palabras marcadas como difíciles por el aprendiz (RF19, HU02)
CREATE TABLE IF NOT EXISTS vocabulario_marcado (
    usuario_id    VARCHAR(36) NOT NULL,
    vocabulario_id VARCHAR(36) NOT NULL,
    marcado_en    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, vocabulario_id),
    CONSTRAINT fk_vmarca_usuario FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)    ON DELETE CASCADE,
    CONSTRAINT fk_vmarca_vocab  FOREIGN KEY (vocabulario_id) REFERENCES vocabulario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DIÁLOGOS CLÍNICOS
-- =============================================================

-- Escenario de diálogo clínico (RF24)
CREATE TABLE IF NOT EXISTS dialogo (
    id               VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    rap_id           VARCHAR(36)  NOT NULL,
    titulo           VARCHAR(255) NOT NULL,
    contexto         VARCHAR(500) NULL,
    participantes    VARCHAR(255) NULL,
    audio_completo_url VARCHAR(255) NULL,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_dialogo_rap FOREIGN KEY (rap_id) REFERENCES rap(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turnos del diálogo sincronizados (RF25)
CREATE TABLE IF NOT EXISTS turno_dialogo (
    id          VARCHAR(36)   NOT NULL DEFAULT (UUID()),
    dialogo_id  VARCHAR(36)   NOT NULL,
    orden_turno INT           NOT NULL,
    hablante    VARCHAR(100)  NOT NULL,
    texto_en    VARCHAR(1000) NOT NULL,
    texto_es    VARCHAR(1000) NOT NULL,
    audio_url   VARCHAR(255)  NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_turno_dialogo FOREIGN KEY (dialogo_id) REFERENCES dialogo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- EJERCICIOS INTERACTIVOS
-- =============================================================

-- Ejercicios de práctica por RAP (RF21, RF22)
CREATE TABLE IF NOT EXISTS ejercicio (
    id               VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    rap_id           VARCHAR(36)  NOT NULL,
    vocab_ayuda_id   VARCHAR(36)  NULL,   -- vocabulario relacionado de ayuda
    tipo             ENUM(
                        'seleccion_multiple',
                        'completar_frase',
                        'arrastrar_soltar',
                        'ordenar_dialogo',
                        'escucha_escribe',
                        'role_play'
                     ) NOT NULL,
    enunciado        VARCHAR(1000) NOT NULL,
    max_intentos     INT           NOT NULL DEFAULT 3,
    puntos           INT           NOT NULL DEFAULT 10,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_ejercicio_rap   FOREIGN KEY (rap_id)         REFERENCES rap(id),
    CONSTRAINT fk_ejercicio_vocab FOREIGN KEY (vocab_ayuda_id) REFERENCES vocabulario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opciones de respuesta para ejercicios (estructura JSON para mayor flexibilidad)
-- Se guarda en la tabla ejercicio_opcion para normalizar
CREATE TABLE IF NOT EXISTS ejercicio_opcion (
    id           VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    ejercicio_id VARCHAR(36)  NOT NULL,
    texto        VARCHAR(500) NOT NULL,
    es_correcta  TINYINT(1)  NOT NULL DEFAULT 0,
    retroalimentacion VARCHAR(300) NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_opcion_ejercicio FOREIGN KEY (ejercicio_id) REFERENCES ejercicio(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos del aprendiz en ejercicios (RF23)
CREATE TABLE IF NOT EXISTS intento_ejercicio (
    id                VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    ejercicio_id      VARCHAR(36)  NOT NULL,
    usuario_id        VARCHAR(36)  NOT NULL,
    respuesta_elegida VARCHAR(500) NULL,
    es_correcto       TINYINT(1)   NOT NULL DEFAULT 0,
    numero_intento    INT          NOT NULL DEFAULT 1,
    tiempo_respuesta_ms INT        NULL,
    creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_intento_ejercicio FOREIGN KEY (ejercicio_id) REFERENCES ejercicio(id),
    CONSTRAINT fk_intento_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- QUIZZES DE EVALUACIÓN
-- =============================================================

-- Quiz al cierre de cada RAP (RF26)
CREATE TABLE IF NOT EXISTS quiz (
    id                  VARCHAR(36)   NOT NULL DEFAULT (UUID()),
    rap_id              VARCHAR(36)   NOT NULL UNIQUE,  -- un quiz por RAP
    puntaje_minimo      DECIMAL(5,2)  NOT NULL DEFAULT 60.00,
    limite_tiempo_seg   INT           NULL,
    aleatorizar         TINYINT(1)    NOT NULL DEFAULT 0,
    max_intentos        INT           NOT NULL DEFAULT 3,
    activo              TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_quiz_rap FOREIGN KEY (rap_id) REFERENCES rap(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preguntas del quiz
CREATE TABLE IF NOT EXISTS pregunta (
    id              VARCHAR(36)   NOT NULL DEFAULT (UUID()),
    quiz_id         VARCHAR(36)   NOT NULL,
    texto           VARCHAR(1000) NOT NULL,
    opciones        JSON          NOT NULL,    -- array de opciones
    respuesta_correcta VARCHAR(255) NOT NULL,
    retroalimentacion VARCHAR(500) NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_pregunta_quiz FOREIGN KEY (quiz_id) REFERENCES quiz(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intento del aprendiz en el quiz (RF28)
CREATE TABLE IF NOT EXISTS intento_quiz (
    id               VARCHAR(36)   NOT NULL DEFAULT (UUID()),
    quiz_id          VARCHAR(36)   NOT NULL,
    usuario_id       VARCHAR(36)   NOT NULL,
    puntaje          DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    aprobado         TINYINT(1)    NOT NULL DEFAULT 0,
    numero_intento   INT           NOT NULL DEFAULT 1,
    duracion_seg     INT           NULL,
    creado_en        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_iquiz_quiz    FOREIGN KEY (quiz_id)    REFERENCES quiz(id),
    CONSTRAINT fk_iquiz_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respuesta individual por pregunta en cada intento (RF27)
CREATE TABLE IF NOT EXISTS respuesta_quiz (
    id               VARCHAR(36)  NOT NULL DEFAULT (UUID()),
    intento_quiz_id  VARCHAR(36)  NOT NULL,
    pregunta_id      VARCHAR(36)  NOT NULL,
    respuesta_elegida VARCHAR(500) NULL,
    es_correcto      TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_rquiz_intento  FOREIGN KEY (intento_quiz_id) REFERENCES intento_quiz(id) ON DELETE CASCADE,
    CONSTRAINT fk_rquiz_pregunta FOREIGN KEY (pregunta_id)      REFERENCES pregunta(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DATOS INICIALES OBLIGATORIOS
-- =============================================================

-- Programa de formación del SENA
INSERT INTO programa_formacion (id, nombre, descripcion) VALUES
(UUID(), 'Técnico en Enfermería', 'Programa técnico de enfermería del SENA - Regional Departamento');

-- 6 Niveles fijos alineados al MCER (A1 → B2)
INSERT INTO nivel (id, nombre, descripcion, orden, activo, umbral_desbloqueo) VALUES
(UUID(), 'Nivel 1 - A1 Básico',       'Fundamentos del inglés clínico. Saludos, presentaciones y vocabulario esencial de enfermería.',    1, 1, 0.00),
(UUID(), 'Nivel 2 - A2 Elemental',    'Comunicación elemental en contextos de atención al paciente.',                                       2, 1, 80.00),
(UUID(), 'Nivel 3 - B1 Pre-intermedio','Interacciones clínicas más complejas: signos vitales, procedimientos y reporte de turno.',          3, 1, 80.00),
(UUID(), 'Nivel 4 - B1+ Intermedio',  'Documentación clínica, formularios y comunicación con el equipo médico.',                           4, 1, 80.00),
(UUID(), 'Nivel 5 - B2 Pre-avanzado', 'Escenarios de emergencia, farmacología y comunicación especializada.',                              5, 1, 80.00),
(UUID(), 'Nivel 6 - B2 Avanzado',     'Dominio completo de la comunicación clínica en inglés para enfermería profesional.',                6, 1, 80.00);

-- Un RAP por nivel (contenido lo carga el administrador)
INSERT INTO rap (id, nivel_id, titulo, orden, activo)
SELECT UUID(), id, CONCAT('RAP ', orden, ' — ', nombre), 1, 1 FROM nivel ORDER BY orden;

-- Áreas clínicas base
INSERT INTO area_clinica (id, nombre) VALUES
(UUID(), 'General'), (UUID(), 'Cardiología'), (UUID(), 'Urgencias'),
(UUID(), 'Farmacología'), (UUID(), 'Cirugía'), (UUID(), 'Pediatría'),
(UUID(), 'UCI'), (UUID(), 'Obstetricia'), (UUID(), 'Traumatología');

-- Categorías de vocabulario base
INSERT INTO categoria_vocabulario (id, nombre) VALUES
(UUID(), 'Sustantivo'), (UUID(), 'Verbo'), (UUID(), 'Adjetivo'),
(UUID(), 'Adverbio'), (UUID(), 'Frase'), (UUID(), 'Abreviatura');

-- Insignias base del sistema (RF35)
INSERT INTO insignia (id, nombre, descripcion, criterio) VALUES
(UUID(), 'Primer Nivel',      'Completaste tu primer nivel',                 'Completar Nivel 1'),
(UUID(), 'Racha 7 Días',      '7 días consecutivos de práctica',             'racha_dias >= 7'),
(UUID(), 'Quiz Perfecto',     'Obtuviste 100% en un quiz',                   'puntaje_quiz = 100'),
(UUID(), 'Vocabulario Pro',   'Aprendiste 50 palabras médicas',              'vocabulario_aprendido >= 50'),
(UUID(), 'Estudiante Élite',  'Completaste todos los niveles',               'niveles_completados = 6');

-- Administrador por defecto (contraseña: Admin@2025 — cambiar en producción)
-- Hash bcrypt generado con cost 12
INSERT INTO usuarios (id, nombre_completo, correo, contrasena, rol, activo, correo_verificado)
VALUES (
    UUID(),
    'Administrador SENA',
    'admin@smashcode.edu.co',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@2025
    'admin',
    1,
    1
);
