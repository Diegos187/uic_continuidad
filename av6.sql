-- --------------------------------------------------------
-- Base de datos: utem_curriculum
-- --------------------------------------------------------
DROP DATABASE IF EXISTS `utem_curriculum`;
CREATE DATABASE `utem_curriculum` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `utem_curriculum`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Tabla: usuarios
-- --------------------------------------------------------
CREATE TABLE usuarios (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuarios (id, nombre, email, password, role, created_at)
VALUES (1, 'UserTest', 'test@test.cl', '$2y$10$8vomR.gu3T0uOwwkc.aPZOoFiullzF8UDCGdod.w4kvSGFB8J2Ngi', 1, NOW());

-- --------------------------------------------------------
-- Tabla: carreras
-- --------------------------------------------------------
CREATE TABLE carreras (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(200) NOT NULL,
  jornada ENUM('Diurna','Vespertina') NOT NULL,
  duracion_semestres INT(11) NOT NULL,
  anio INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: asignaturas
-- --------------------------------------------------------
CREATE TABLE asignaturas (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(200) NOT NULL,
  carrera_id INT(11) NOT NULL,
  semestre INT(11) NOT NULL,
  duracion_semanas INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY carrera_id (carrera_id),
  CONSTRAINT asignaturas_ibfk_1 FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: archivos
-- --------------------------------------------------------
CREATE TABLE archivos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(255) NOT NULL,
  descripcion TEXT NOT NULL,
  tipo VARCHAR(100) NOT NULL,
  contenido LONGTEXT NOT NULL,
  usuario_id INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY usuario_id (usuario_id),
  CONSTRAINT archivos_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: perfiles_egreso
-- --------------------------------------------------------
CREATE TABLE perfiles_egreso (
  id INT(11) NOT NULL AUTO_INCREMENT,
  carrera_id INT(11) NOT NULL,
  descripcion TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY carrera_id (carrera_id),
  CONSTRAINT perfiles_egreso_ibfk_1 FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: áreas de formación
-- --------------------------------------------------------
CREATE TABLE areas_formacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: carrera_area_formacion
-- --------------------------------------------------------
CREATE TABLE carrera_area_formacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  carrera_id INT(11) NOT NULL,
  area_formacion_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  KEY carrera_id (carrera_id),
  KEY area_formacion_id (area_formacion_id),
  CONSTRAINT fk_carrera_area FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE,
  CONSTRAINT fk_area_carrera FOREIGN KEY (area_formacion_id) REFERENCES areas_formacion (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: perfiles_egreso_detalle
-- --------------------------------------------------------
CREATE TABLE perfiles_egreso_detalle (
  id INT(11) NOT NULL AUTO_INCREMENT,
  perfil_egreso_id INT(11) NOT NULL,
  area_formacion_id INT(11) DEFAULT NULL,
  dominio TEXT NOT NULL,
  competencia TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY perfil_egreso_id (perfil_egreso_id),
  KEY area_formacion_id (area_formacion_id),
  CONSTRAINT fk_pe_det_perfil FOREIGN KEY (perfil_egreso_id) REFERENCES perfiles_egreso (id) ON DELETE CASCADE,
  CONSTRAINT fk_pe_det_area FOREIGN KEY (area_formacion_id) REFERENCES areas_formacion (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: competencias_dominio (NUEVA)
-- --------------------------------------------------------
CREATE TABLE competencias_dominio (
  id INT(11) NOT NULL AUTO_INCREMENT,
  perfil_egreso_detalle_id INT(11) NOT NULL,
  codigo VARCHAR(10) NOT NULL,
  descripcion TEXT NOT NULL,
  orden INT(11) DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY perfil_egreso_detalle_id (perfil_egreso_detalle_id),
  CONSTRAINT fk_comp_dominio FOREIGN KEY (perfil_egreso_detalle_id) 
    REFERENCES perfiles_egreso_detalle (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: resultados_aprendizaje_ref (NUEVA)
-- --------------------------------------------------------
CREATE TABLE resultados_aprendizaje_ref (
  id INT(11) NOT NULL AUTO_INCREMENT,
  competencia_dominio_id INT(11) NOT NULL,
  codigo VARCHAR(10) NOT NULL,
  descripcion TEXT NOT NULL,
  orden INT(11) DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY competencia_dominio_id (competencia_dominio_id),
  CONSTRAINT fk_ra_competencia FOREIGN KEY (competencia_dominio_id) 
    REFERENCES competencias_dominio (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: criterios_logro_ref (NUEVA)
-- --------------------------------------------------------
CREATE TABLE criterios_logro_ref (
  id INT(11) NOT NULL AUTO_INCREMENT,
  resultado_aprendizaje_id INT(11) NOT NULL,
  codigo VARCHAR(10) NOT NULL,
  descripcion TEXT NOT NULL,
  orden INT(11) DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY resultado_aprendizaje_id (resultado_aprendizaje_id),
  CONSTRAINT fk_cl_resultado FOREIGN KEY (resultado_aprendizaje_id) 
    REFERENCES resultados_aprendizaje_ref (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: matrices (SIN DEPENDENCIAS DE versiones_matriz)
-- --------------------------------------------------------
CREATE TABLE matrices (
  id INT(11) NOT NULL AUTO_INCREMENT,
  carrera_id INT(11) NOT NULL,
  version_id INT(11) DEFAULT NULL,
  nombre VARCHAR(255) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY carrera_id (carrera_id),
  CONSTRAINT fk_matriz_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: versiones_matriz
-- --------------------------------------------------------
CREATE TABLE versiones_matriz (
  id INT(11) NOT NULL AUTO_INCREMENT,
  matriz_id INT(11) NOT NULL,
  carrera_id INT(11) NOT NULL,
  numero_version INT(11) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY matriz_id (matriz_id),
  KEY carrera_id (carrera_id),
  CONSTRAINT fk_version_matriz FOREIGN KEY (matriz_id) REFERENCES matrices (id) ON DELETE CASCADE,
  CONSTRAINT versiones_matriz_ibfk_1 FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Agregar constraint de version_id en matrices
-- --------------------------------------------------------
ALTER TABLE matrices ADD CONSTRAINT fk_matriz_version_general FOREIGN KEY (version_id) REFERENCES versiones_matriz (id) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Tabla: matrices_coherencia
-- --------------------------------------------------------
CREATE TABLE matrices_coherencia (
  id INT(11) NOT NULL AUTO_INCREMENT,
  matriz_id INT(11) DEFAULT NULL,
  asignatura_id INT(11) NOT NULL,
  area_formacion_id INT(11) DEFAULT NULL,
  perfil_egreso_id INT(11) DEFAULT NULL,
  perfil_egreso_detalle_id INT(11) DEFAULT NULL, -- NUEVA: dominio específico dentro del área
  version_id INT(11) DEFAULT NULL,
  dominio TEXT NOT NULL,
  competencia TEXT NOT NULL,
  resultado_aprendizaje TEXT NOT NULL,
  criterios_logro TEXT NOT NULL,
  bibliografia TEXT DEFAULT NULL,
  metodologias TEXT DEFAULT NULL,
  contenidos TEXT DEFAULT NULL,
  estrategias TEXT DEFAULT NULL,
  sct_chile INT(11) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY matriz_id (matriz_id),
  KEY asignatura_id (asignatura_id),
  KEY area_formacion_id (area_formacion_id),
  KEY perfil_egreso_id (perfil_egreso_id),
  KEY perfil_egreso_detalle_id (perfil_egreso_detalle_id),
  KEY version_id (version_id),
  CONSTRAINT fk_matriz_coherencia_grupo FOREIGN KEY (matriz_id) REFERENCES matrices (id) ON DELETE CASCADE,
  CONSTRAINT matrices_coherencia_ibfk_1 FOREIGN KEY (asignatura_id) REFERENCES asignaturas (id) ON DELETE CASCADE,
  CONSTRAINT fk_matriz_area FOREIGN KEY (area_formacion_id) REFERENCES areas_formacion (id) ON DELETE SET NULL,
  CONSTRAINT fk_matriz_perfil FOREIGN KEY (perfil_egreso_id) REFERENCES perfiles_egreso (id) ON DELETE SET NULL,
  CONSTRAINT fk_matriz_perfil_detalle FOREIGN KEY (perfil_egreso_detalle_id) REFERENCES perfiles_egreso_detalle (id) ON DELETE SET NULL,
  CONSTRAINT fk_matriz_version FOREIGN KEY (version_id) REFERENCES versiones_matriz (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabla: matriz_tributacion (NUEVA)
-- --------------------------------------------------------
CREATE TABLE matriz_tributacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  matriz_coherencia_id INT(11) NOT NULL,
  criterio_logro_id INT(11) NOT NULL,
  marcado BOOLEAN DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY unique_matriz_tributacion (matriz_coherencia_id, criterio_logro_id),
  KEY criterio_logro_id (criterio_logro_id),
  CONSTRAINT fk_tributacion_matriz FOREIGN KEY (matriz_coherencia_id) 
    REFERENCES matrices_coherencia (id) ON DELETE CASCADE,
  CONSTRAINT fk_tributacion_criterio FOREIGN KEY (criterio_logro_id) 
    REFERENCES criterios_logro_ref (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;