CREATE DATABASE IF NOT EXISTS clinica_salud_local;

USE clinica_salud_local;

CREATE TABLE IF NOT EXISTS servicio_medico (
    id_servicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    costo DECIMAL(10,2) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo'
);

INSERT INTO servicio_medico (nombre_servicio, descripcion, costo, estado) VALUES
('Medicina General', 'Atención médica para consultas generales, control de síntomas y revisión preventiva del paciente.', 20000.00, 'Activo'),
('Odontología', 'Servicio para revisión dental, limpiezas, tratamientos básicos y prevención de enfermedades bucales.', 25000.00, 'Activo'),
('Psicología', 'Atención profesional para apoyo emocional, orientación personal y seguimiento psicológico.', 30000.00, 'Activo'),
('Pediatría', 'Atención médica dirigida a niños y adolescentes para control, seguimiento y prevención.', 25000.00, 'Activo'),
('Nutrición', 'Evaluación nutricional y recomendaciones alimenticias para mejorar la salud del paciente.', 22000.00, 'Activo'),
('Laboratorio Clínico', 'Realización de exámenes básicos de laboratorio para apoyar el diagnóstico médico.', 15000.00, 'Activo');