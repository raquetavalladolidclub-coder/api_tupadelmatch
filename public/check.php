<?php
echo "<h1>Verificación de Instalación Padel Club API</h1>";

// 1. Verificar PHP
echo "<h2>1. Versión de PHP: " . PHP_VERSION . "</h2>";

// 2. Verificar extensiones
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
echo "<h2>2. Extensiones requeridas:</h2>";
foreach ($required_extensions as $ext) {
    echo extension_loaded($ext) ? "✅ $ext<br>" : "❌ $ext - FALTANTE<br>";
}

// 3. Verificar permisos
echo "<h2>3. Permisos de archivos:</h2>";
$files_to_check = [
    '.env' => is_readable('.env'),
    'vendor/' => is_dir('vendor'),
    'public/index.php' => file_exists('public/index.php')
];

foreach ($files_to_check as $file => $exists) {
    echo $exists ? "✅ $file<br>" : "❌ $file<br>";
}

// 4. Verificar composer
echo "<h2>4. Verificar Composer:</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "✅ vendor/autoload.php existe<br>";
    
    require 'vendor/autoload.php';
    echo "✅ Autoload cargado correctamente<br>";
} else {
    echo "❌ vendor/autoload.php no existe - Ejecuta: composer install<br>";
}

// 5. Probar base de datos
echo "<h2>5. Conexión a base de datos:</h2>";
try {
    if (file_exists('.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }
    
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'padel_club';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", 
                   $_ENV['DB_USER'] ?? 'root', 
                   $_ENV['DB_PASS'] ?? '');
    echo "✅ Conexión a BD exitosa<br>";
} catch (Exception $e) {
    echo "❌ Error BD: " . $e->getMessage() . "<br>";
}

echo "<h2>6. URLs de prueba:</h2>";
$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
echo "<a href='https://$domain/public/version' target='_blank'>/version</a><br>";
echo "<a href='https://$domain/public/health' target='_blank'>/health</a><br>";