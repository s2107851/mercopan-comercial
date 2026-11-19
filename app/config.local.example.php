<?php
// Guardar como config.local.php (NUNCA subir a GitHub).
return [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'mercopan_comercial',
    'db_user' => 'mercopan_app',
    'db_pass' => 'CAMBIAR_ESTO',
    // Generar: php -r "echo password_hash('TU_CLAVE_SEGURA', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_password_hash' => 'PEGAR_AQUI_EL_HASH',
    'ocr_languages' => 'spa+eng',
    'ocr_tesseract' => 'tesseract', // en Windows: ruta completa a tesseract.exe
    'pdf_pdftoppm' => 'pdftoppm', // en Windows: ruta completa a pdftoppm.exe
    'max_upload_bytes' => 10485760,
    'session_secure' => false, // true bajo HTTPS
];
