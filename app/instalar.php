<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function comprobarBaseExistente(PDO $db, string $schema): void {
    $referencia = 'mp_revision_'.bin2hex(random_bytes(12));
    $db->exec("CREATE DATABASE `$referencia` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try {
        $import = $db->query(str_replace('mercopan_comercial', $referencia, $schema));
        while ($import->nextRowset()) {}
        $import->closeCursor();
        $tablas = $db->query("SHOW TABLES FROM `$referencia`")->fetchAll(PDO::FETCH_COLUMN);
        $actuales = $db->query('SHOW TABLES FROM mercopan_comercial')->fetchAll(PDO::FETCH_COLUMN);
        sort($tablas);
        sort($actuales);
        if ($tablas !== $actuales) throw new RuntimeException('Las tablas existentes difieren del esquema Mercopan. Se requiere revision manual.');
        foreach ($tablas as $tabla) {
            $estructura = static function (string $base) use ($db, $tabla): string {
                $ddl = $db->query("SHOW CREATE TABLE `$base`.`$tabla`")->fetch(PDO::FETCH_NUM)[1];
                return preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $ddl);
            };
            if ($estructura($referencia) !== $estructura('mercopan_comercial')) {
                throw new RuntimeException("La tabla $tabla difiere del esquema esperado. Se conservaron los datos.");
            }
        }
    } finally {
        $db->exec("DROP DATABASE `$referencia`");
    }
}

function instalar(string &$paso): void {
    $configPath = __DIR__.'/config.local.php';
    $pending = __DIR__.'/instalacion-pendiente.php';
    if (is_file($configPath)) {
        require __DIR__.'/config.php';
        $GLOBALS['config'] = $config;
        require __DIR__.'/core.php';
        $paso = 'verificar la conexion guardada';
        sql('SELECT id FROM empleados LIMIT 1');
        echo "Mercopan ya esta configurado y se pudo conectar.\n";
        return;
    }
    if (PHP_VERSION_ID < 80100 || !extension_loaded('pdo_mysql') || !extension_loaded('fileinfo')) {
        throw new RuntimeException('Se necesita PHP 8.1 o superior con pdo_mysql y fileinfo.');
    }
    $admin = (string)getenv('MERCOPAN_ADMIN');
    if (strlen($admin)<12 || strlen($admin)>72) throw new RuntimeException('La clave de Mercopan debe ocupar entre 12 y 72 bytes.');
    $port = filter_var(getenv('MERCOPAN_PORT') ?: 3306,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
    if (!$port) throw new RuntimeException('Puerto MySQL incorrecto.');
    $dsn = "mysql:host=127.0.0.1;port=$port;charset=utf8mb4";
    $paso = 'conectar con root al puerto local '.$port;
    $db = new PDO($dsn,'root',(string)getenv('MERCOPAN_SQL'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $paso = 'comprobar la base existente';
    if ((int)$db->query("SELECT GET_LOCK('mercopan_comercial_instalar',0)")->fetchColumn()!==1) throw new RuntimeException('Ya hay otra instalacion en curso.');
    if (is_file($configPath)) throw new RuntimeException('La instalacion ya se completo.');
    $schema = file_get_contents(dirname(__DIR__).'/database/001_schema.sql');
    if (!$schema) throw new RuntimeException('Falta el esquema SQL.');
    $exists = (bool)$db->query("SELECT 1 FROM information_schema.schemata WHERE schema_name='mercopan_comercial'")->fetchColumn();
    umask(0077);
    $reanudar = is_file($pending);
    if (!$reanudar) {
        $paso = 'comparar las tablas con el esquema de Mercopan';
        if ($exists) comprobarBaseExistente($db, $schema);
        $cfg = require __DIR__.'/config.local.example.php';
        $cfg['db_user'] = 'mp_'.bin2hex(random_bytes(8));
        $cfg['db_pass'] = bin2hex(random_bytes(24));
        $cfg['db_port'] = $port;
        $cfg['reusar_base'] = $exists;
        $cfg['admin_password_hash'] = password_hash($admin,PASSWORD_DEFAULT);
        $data = "<?php\nreturn ".var_export($cfg,true).";\n";
        if (file_put_contents($pending,$data,LOCK_EX)!==strlen($data)) throw new RuntimeException('No se pudo guardar la configuracion provisional.');
    }
    $cfg = require $pending;
    if ($cfg['db_port']!==$port || !password_verify($admin,$cfg['admin_password_hash'])) throw new RuntimeException('Para reanudar, usa la clave de Mercopan y el puerto de la primera ejecucion.');
    $marker = 'inst_'.hash('sha256',$cfg['db_pass']);
    $marker = substr($marker,0,48);
    $reusar = $cfg['reusar_base'] ?? false;
    if ($reusar) {
        if (!$exists) throw new RuntimeException('Ya no se encuentra la base que se iba a conectar.');
        $paso = 'validar la estructura existente';
        if ($reanudar) comprobarBaseExistente($db, $schema);
    } elseif ($exists) {
        $q=$db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema='mercopan_comercial' AND table_name=?");
        $q->execute([$marker]);
        if (!$q->fetchColumn()) throw new RuntimeException('La base existente no pertenece a esta instalacion. Se requiere revision manual.');
    } else {
        $paso = 'crear la base de Mercopan';
        $db->exec('CREATE DATABASE mercopan_comercial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $db->exec("CREATE TABLE mercopan_comercial.`$marker` (id INT PRIMARY KEY) ENGINE=InnoDB");
    }
    if (!$reusar) {
        $paso = 'importar las tablas';
        $import = $db->query($schema);
        while ($import->nextRowset()) { /* Consumir todos los resultados del esquema. */ }
        $import->closeCursor();
    }
    $account=$db->quote($cfg['db_user'])."@'127.0.0.1'";
    $paso = 'crear el usuario de Mercopan';
    $db->exec("CREATE USER IF NOT EXISTS $account IDENTIFIED BY ".$db->quote($cfg['db_pass']));
    $paso = 'asignar permisos al usuario de Mercopan';
    $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `mercopan\\_comercial`.* TO $account");
    $paso = 'probar la conexion del usuario de Mercopan';
    $app = new PDO($dsn.';dbname=mercopan_comercial',$cfg['db_user'],$cfg['db_pass']);
    $app->query('SELECT id FROM auditoria LIMIT 1');
    if (!rename($pending,$configPath)) throw new RuntimeException('No se pudo activar la configuracion. Volve a ejecutar el instalador.');
    if ($reusar) {
        echo "Base existente conectada. Podes abrir Administracion.\n";
        return;
    }
    $db->exec("DROP TABLE mercopan_comercial.`$marker`");
    echo "Base de Mercopan creada. Entra a Administracion y crea el primer empleado.\n";
}
$paso = 'iniciar';
try { instalar($paso); }
catch (Throwable $e) {
    $mensaje = $e->getMessage();
    if ($e instanceof PDOException) {
        $numero = $e->errorInfo[1] ?? $e->getCode();
        // Los errores de sintaxis pueden reproducir SQL con credenciales.
        $detalle = match ((int)$numero) {
            1044, 1142, 1227 => 'La cuenta no tiene permisos suficientes para este paso.',
            1045, 1698 => 'MariaDB rechazo la autenticacion de la cuenta.',
            2002, 2003 => 'No se pudo conectar con el servicio en el puerto seleccionado.',
            default => $paso === 'crear el usuario de Mercopan'
                ? 'MariaDB rechazo la creacion de la cuenta.'
                : ($e->errorInfo[2] ?? $mensaje),
        };
        $mensaje = "Error MySQL $numero al $paso. $detalle";
    }
    fwrite(STDERR, $mensaje."\n");
    exit(1);
}
