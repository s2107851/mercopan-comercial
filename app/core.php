<?php
declare(strict_types=1);
function conn(): PDO {
    static $db = null;
    global $config;
    if ($db instanceof PDO) return $db;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
    $db = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $db->exec("SET time_zone='-03:00'");
    return $db;
}
function sql(string $s, array $params=[]): PDOStatement {
    $stmt=conn()->prepare($s); $stmt->execute($params); return $stmt;
}
function all(string $s, array $params=[]): array { return sql($s,$params)->fetchAll(); }
function one(string $s, array $params=[]): ?array { return sql($s,$params)->fetch() ?: null; }
function trans(callable $fn): mixed {
    $db=conn(); $db->beginTransaction();
    try { $r=$fn(); $db->commit(); return $r; }
    catch(Throwable $e){ if($db->inTransaction()) $db->rollBack(); throw $e; }
}
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(mixed $v): string { return number_format((float)$v,2,',','.'); }
function num(mixed $v): string { return number_format((float)$v,3,',','.'); }
function fail(string $s): never { throw new DomainException($s); }
function check(bool $v, string $msg): void { if (!$v) fail($msg); }
function positiveQty(mixed $v, bool $integer=false): string {
    $s=str_replace(',','.',trim((string)$v));
    check((bool)preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D',$s), 'Cantidad inválida: usá hasta tres decimales.');
    $n=(float)$s;
    check($n>0 && $n<=9999999, 'La cantidad debe ser mayor a cero.');
    check(!$integer || floor($n)===$n, 'Este artículo se registra por unidades enteras.');
    return number_format($n,3,'.','');
}
function nonnegativeQty(mixed $v): string {
    $s=str_replace(',','.',trim((string)$v));
    check((bool)preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D',$s),'Cantidad inválida.');
    return number_format((float)$s,3,'.','');
}
function amount(mixed $v, bool $allowZero=true): string {
    $s=str_replace(',','.',trim((string)$v));
    check((bool)preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$s),'Importe inválido (máximo dos decimales).');
    $f=(float)$s;
    check($f<=9999999999.99 && ($allowZero || $f>0),'Importe fuera de rango.');
    return number_format($f,2,'.','');
}
function textField(mixed $v,int $max=120,bool $required=true): string {
    check(is_string($v),'Texto inválido.'); $s=trim($v);
    check((!$required||$s!=='') && mbLen($s)<=$max,'Falta un dato o excede el largo permitido.');
    return $s;
}
function mbLen(string $v): int { return function_exists('mb_strlen')?mb_strlen($v):strlen($v); }
function recordId(mixed $v): int {
    check(is_scalar($v) && preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v)===1 && (float)$v<=2147483647,'Identificador inválido.');
    return (int)$v;
}
function safeDate(mixed $v): string {
    $s=textField($v,10); $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$s);
    check($dt!==false && $dt->format('Y-m-d')===$s,'Fecha inválida.'); return $s;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function verifyCsrf(): void { check(isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals(csrf(),$_POST['csrf']),'Sesión vencida. Recargá la página.'); }
function employee(): ?array {
    $id=$_SESSION['empleado_id']??0;
    return $id ? one('SELECT id,nombre,rol FROM empleados WHERE id=? AND activo=1',[$id]):null;
}
function requireEmployee(): array { return employee() ?? (fail('Elegí un empleado antes de registrar movimientos.')); }
function selectEmployee(int $id): void {
    $p=one('SELECT id FROM empleados WHERE id=? AND activo=1',[$id]);
    check($p!==null,'Empleado inexistente o inactivo.');
    $_SESSION['empleado_id']=$id;
    audit('seleccionar_responsable','empleados',$id,[],['empleado_id'=>$id]);
}
function adminOK(): bool { return isset($_SESSION['admin_auth']) && $_SESSION['admin_auth']===true; }
function requireAdmin(): void { if(!adminOK()){ http_response_code(403); fail('Acceso administrativo restringido.'); } }
function authAdmin(string $password): bool {
    global $config;
    $hash=(string)($config['admin_password_hash']??'');
    if(!str_starts_with($hash,'$2') && !str_starts_with($hash,'$argon')) return false;
    if(!password_verify($password,$hash)) return false;
    session_regenerate_id(true); $_SESSION['admin_auth']=true;
    return true;
}
function audit(string $event,string $entity,int $id,array $before=[],array $after=[]): void {
    $emp=employee();
    sql('INSERT INTO auditoria (empleado_id,evento,entidad,entidad_id,antes,despues,ip) VALUES (?,?,?,?,?,?,?)',[
      $emp['id']??null,$event,$entity,$id,json_encode($before,JSON_UNESCAPED_UNICODE),json_encode($after,JSON_UNESCAPED_UNICODE),substr($_SERVER['REMOTE_ADDR']??'local',0,45)
    ]);
}
function actionKey(mixed $v,string $type,int $employee): void {
    check(is_string($v) && preg_match('/^[a-f0-9-]{20,64}$/iD',$v)===1,'Operación inválida. Recargá la pantalla.');
    // UNIQUE impide reenvíos y carreras entre pantallas sobre una misma acción.
    try{sql('INSERT INTO operaciones (clave,tipo,empleado_id) VALUES (?,?,?)',[$v,$type,$employee]);}
    catch(PDOException $e){if($e->getCode()==='23000') fail('Esta operación ya se registró.');throw $e;}
}
function redirect(string $page,string $msg=''): never {
    if($msg!=='') $_SESSION['flash']=$msg;
    header('Location: '.$page, true,303);exit;
}
function flash(): string { $m=(string)($_SESSION['flash']??'');unset($_SESSION['flash']);return $m; }
function jsonReply(array $payload,int $status=200): never {
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
function postRun(callable $handler): never {
    try { check($_SERVER['REQUEST_METHOD']==='POST','Método no permitido.');verifyCsrf(); $res=$handler();jsonReply(['ok'=>true,'data'=>$res]); }
    catch(DomainException $e){jsonReply(['ok'=>false,'error'=>$e->getMessage()],422);}
    catch(Throwable $e){error_log((string)$e);jsonReply(['ok'=>false,'error'=>'No se pudo completar la operación. Revisá los datos o consultá al administrador.'],500);}
}
function selectOptions(array $values,mixed $selected=null,string $key='id',string $label='nombre'): string {
    $html='';foreach($values as $v){$k=(string)$v[$key];$html.='<option value="'.e($k).'"'.($k===(string)$selected?' selected':'').'>'.e($v[$label]).'</option>';}
    return $html;
}
