<?php
// Configuraciones generales de la aplicación
define('APP_NAME', 'UTEM Curriculum');
define('APP_VERSION', '1.0.0');

// Configuraciones de base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'utem_curriculum');

// Rutas del sistema
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('SRC_PATH', ROOT_PATH . '/src');

// Configuraciones de seguridad
define('SALT', 'tuSaltPersonalizado123!@#');

// Modo de desarrollo o producción
define('DEBUG_MODE', true);

// Niveles de error
error_reporting(DEBUG_MODE ? E_ALL : 0);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);