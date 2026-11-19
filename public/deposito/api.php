<?php
declare(strict_types=1);
require __DIR__.'/../../app/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $data=all('SELECT id,nombre,unidad,categoria,icono,atajos,stock_deposito AS stock FROM ingredientes WHERE activo=1 ORDER BY categoria,nombre');
    jsonReply(['ok'=>true,'items'=>$data,'empleado'=>employee()]);
}
postRun(function(){
    $action=$_POST['action']??'';
    if($action==='empleado'){selectEmployee(recordId($_POST['id']??null));return ['mensaje'=>'Responsable seleccionado.'];}
    if($action==='retirar')return withdrawIngredient($_POST['id']??null,$_POST['cantidad']??null,$_POST['clave']??null);
    if($action==='revertir')return undoWithdraw($_POST['clave']??null);
    fail('Acción inexistente.');
});
