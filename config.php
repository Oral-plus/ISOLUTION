<?php
// Configuration file for employee creation script

// Database configuration
define('DB_SERVER', '192.168.2.244');
define('DB_NAME', 'RBOSKY3');
define('DB_USER', 'sa');
define('DB_PASS', 'Sky2022*!');

// Processing configuration
define('BATCH_SIZE', 50);
define('MAX_EXECUTION_TIME', 0); // 0 = no limit

// File paths
define('LOG_FILE_PATH', __DIR__ . '/employee_creation_log.txt');
define('SUMMARY_LOG_PATH', __DIR__ . '/employee_creation_summary.txt');
define('PROCESSED_EMPLOYEES_FILE', __DIR__ . '/processed_employees.json');

// API configuration (if needed)
define('API_TIMEOUT', 30);
define('API_MAX_RETRIES', 3);

// City mapping configuration
$CITY_MAPPING = [
    'medellin' => 'MEDELLÍN',
    'bogota' => 'BOGOTÁ',
    'cali' => 'CALI',
    'barranquilla' => 'BARRANQUILLA',
    'cartagena' => 'CARTAGENA',
    'bucaramanga' => 'BUCARAMANGA',
    'pereira' => 'PEREIRA',
    'manizales' => 'MANIZALES',
    'ibague' => 'IBAGUÉ',
    'cucuta' => 'CÚCUTA',
    'villavicencio' => 'VILLAVICENCIO',
    'monteria' => 'MONTERÍA',
    'valledupar' => 'VALLEDUPAR',
    'pasto' => 'PASTO',
    'neiva' => 'NEIVA'
];

// Default values for employee creation
$DEFAULT_VALUES = [
    'TipoIdentificacion' => 'CC',
    'Zona' => 'Urbana',
    'Cargo' => 'Empleado',
    'Jornada' => 'Normal',
    'TipoVinculacion' => 'Laboral',
    'Eps' => 'GENERAL',
    'Afp' => 'GENERAL',
    'Arl' => 'GENERAL',
    'Activo' => '1',
    'CodActividadEconomica' => 2,
    'CodOrigenRecursos' => 2,
    'CodSLFCanal' => 17,
    'EsNivelGlobal' => 0,
    'Departamento' => 'ANTIOQUIA',
    'Ciudad' => 'MEDELLÍN'
];
?>
