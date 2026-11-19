<?php
declare(strict_types=1);
require __DIR__.'/../../app/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $data=all('SELECT id,nombre,categoria,unidad,icono,atajos,precio,stock FROM productos WHERE activo=1 ORDER BY categoria,nombre');
    jsonReply(['ok'=>true,'items'=>$data,'empleado'=>employee()]);
}
postRun(function(){
    $action=$_POST['action']??'';
    if($action==='empleado'){selectEmployee(recordId($_POST['id']??null));return ['mensaje'=>'Responsable seleccionado.'];}
    if($action==='vender')return sellProduct($_POST['id']??null,$_POST['cantidad']??null,$_POST['clave']??null);
    if($action==='descartar')return discardProduct($_POST['id']??null,$_POST['cantidad']??null,$_POST['motivo']??null,$_POST['clave']??null);
    if($action==='revertir')return undoLastCounter((string)($_POST['tipo']??''),$_POST['clave']??null);
    fail('Acción inexistente.');
});
