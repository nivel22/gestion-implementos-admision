ALTER TABLE actividad
ADD hora TIME NULL AFTER fecha;

ALTER TABLE asignacion
ADD cantidad_custodia INT UNSIGNED NOT NULL DEFAULT 0 AFTER cantidad_perdida;

ALTER TABLE asignacion
MODIFY estado ENUM(
    'Reservada',
    'Entregada',
    'En custodia',
    'Cerrada',
    'Cancelada'
) NOT NULL DEFAULT 'Reservada';

ALTER TABLE movimiento
MODIFY tipo ENUM(
    'Registro',
    'Reserva',
    'Entrega',
    'Cierre',
    'Incidencia',
    'Ajuste',
    'Custodia'
) NOT NULL;
