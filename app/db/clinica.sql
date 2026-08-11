CREATE DATABASE IF NOT EXISTS clinica_salud_local
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE clinica_salud_local;

CREATE TABLE IF NOT EXISTS servicio_medico (
    id_servicio INT UNSIGNED AUTO_INCREMENT,
    nombre_servicio VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    costo DECIMAL(10,2) UNSIGNED NOT NULL,
    estado ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    PRIMARY KEY (id_servicio),
    UNIQUE KEY uk_servicio_medico_nombre (nombre_servicio)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT INTO servicio_medico
    (nombre_servicio, descripcion, costo, estado)
VALUES
    (
        'Medicina General',
        'Atención médica para consultas generales, control de síntomas y revisión preventiva del paciente.',
        20000.00,
        'Activo'
    ),
    (
        'Odontología',
        'Servicio para revisión dental, limpiezas, tratamientos básicos y prevención de enfermedades bucales.',
        25000.00,
        'Activo'
    ),
    (
        'Psicología',
        'Atención profesional para apoyo emocional, orientación personal y seguimiento psicológico.',
        30000.00,
        'Activo'
    ),
    (
        'Pediatría',
        'Atención médica dirigida a niños y adolescentes para control, seguimiento y prevención.',
        25000.00,
        'Activo'
    ),
    (
        'Nutrición',
        'Evaluación nutricional y recomendaciones alimenticias para mejorar la salud del paciente.',
        22000.00,
        'Activo'
    ),
    (
        'Laboratorio Clínico',
        'Realización de exámenes básicos de laboratorio para apoyar el diagnóstico médico.',
        15000.00,
        'Activo'
    )
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    costo = VALUES(costo),
    estado = VALUES(estado);