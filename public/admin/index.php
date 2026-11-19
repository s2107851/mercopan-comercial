<?php
declare(strict_types=1);
require __DIR__.'/../../app/bootstrap.php';
require_once __DIR__.'/../../app/admin_service.php';
require_once __DIR__.'/../../app/reports.php';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        verifyCsrf();$action=(string)($_POST['action']??'');
        if($action==='login'){
            $tries=(int)($_SESSION['login_failures']??0);$started=(int)($_SESSION['login_started']??0);
            if(time()-$started>900){$tries=0;$_SESSION['login_started']=time();}
            check($tries<6,'Demasiados intentos. Cerrá el navegador e intentá más tarde desde una red autorizada.');
            if (!authAdmin((string)($_POST['password']??''))) { $_SESSION['login_failures']=$tries+1; fail('Contraseña incorrecta.'); }
            unset($_SESSION['login_failures']);redirect('index.php','Bienvenido a la administración.');
        }
        if($action==='logout'){requireAdmin();unset($_SESSION['admin_auth']);session_regenerate_id(true);redirect('index.php');}
        requireAdmin();
        if($action==='select_employee'){selectEmployee(recordId($_POST['id']??null));redirect('index.php','Responsable seleccionado.');}
        if($action==='save_catalog'){
            $id=saveCatalog((string)($_POST['type']??''),$_POST);
            $page=match((string)$_POST['type']){'empleados'=>'empleados','proveedores'=>'proveedores','ingredientes'=>'ingredientes',default=>'productos'};
            redirect('?page='.$page,'Registro guardado (#'.$id.').');
        }
        if($action==='save_recipe'){$rid=saveRecipe($_POST);redirect('?page=recetas','Receta guardada (#'.$rid.').');}
        if($action==='produce'){$r=produce($_POST['producto_id']??null,$_POST['lotes']??null,$_POST['clave']??null);redirect('?page=produccion',$r['mensaje']);}
        if($action==='adjust'){$r=adjustInventory((string)($_POST['sector']??''),$_POST['id']??null,$_POST['nuevo_stock']??'',(string)($_POST['motivo']??''),$_POST['clave']??null);redirect('?page=stock',$r['mensaje']);}
        if($action==='upload_invoice'){$id=createInvoiceUpload($_FILES['archivo']??[]);redirect('?page=factura&id='.$id,'Archivo recibido. Revisá la lectura OCR y corregí los datos.');}
        if($action==='save_invoice'){$id=saveInvoice($_POST);redirect('?page=factura&id='.$id,'Borrador guardado. Confirmá únicamente tras verificar todos los datos.');}
        if($action==='confirm_invoice'){$r=confirmInvoice($_POST['id']??null,$_POST['version']??null);redirect('?page=facturas',$r['mensaje']);}
        if($action==='cancel_invoice'){cancelInvoice($_POST['id']??null);redirect('?page=facturas','Borrador cancelado. No modificó stock.');}
        fail('Acción no permitida.');
    }catch(DomainException $e){$_SESSION['flash_error']=$e->getMessage();redirect('index.php'.(isset($_POST['back'])&&preg_match('/^\?page=[a-z_]+(?:&id=\d+)?$/D',(string)$_POST['back'])?$_POST['back']:''));}
    catch(Throwable $e){error_log((string)$e);$_SESSION['flash_error']='No se pudo guardar. Revisá los datos y la base de datos.';redirect('index.php');}
}
if(isset($_GET['archivo'])){
    requireAdmin();$id=recordId($_GET['archivo']);
    $r=one('SELECT archivo FROM facturas WHERE id=?',[$id]);check($r!==null&&$r['archivo']!==null,'Documento no encontrado.');
    $f=basename((string)$r['archivo']);check(preg_match('/^[a-f0-9]{40}\.(pdf|png|jpg)$/D',$f)===1,'Documento inválido.');
    $path=__DIR__.'/../../storage/facturas/'.$f;check(is_file($path),'Documento no encontrado.');
    header('Content-Type: '.(str_ends_with($f,'.pdf')?'application/pdf':(str_ends_with($f,'.png')?'image/png':'image/jpeg')));
    header('Content-Disposition: attachment; filename="factura-'.$id.'.'.pathinfo($f,PATHINFO_EXTENSION).'"');
    header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($path));readfile($path);exit;
}
if(isset($_GET['export'])){
    requireAdmin();
    $mode=(string)$_GET['export'];
    check(in_array($mode,['ventas','descartes','facturas','movimientos'],true),'Exportación inválida.');
    [$since,$end,$until]=dateWindow($_GET['desde']??date('Y-m-01'),$_GET['hasta']??date('Y-m-d'));
    $exportSql=match($mode){
        'ventas'=>"SELECT v.fecha,p.nombre producto,p.unidad,v.cantidad,v.precio_unitario,v.ingreso,e.nombre responsable,IF(v.revertido IS NULL,'vigente','revertida') estado FROM ventas v JOIN productos p ON p.id=v.producto_id JOIN empleados e ON e.id=v.empleado_id WHERE v.fecha>=? AND v.fecha<? ORDER BY v.fecha,v.id LIMIT 20000",
        'descartes'=>"SELECT d.fecha,p.nombre producto,p.unidad,d.cantidad,d.motivo,d.costo_total,e.nombre responsable,IF(d.revertido IS NULL,'vigente','revertida') estado FROM descartes d JOIN productos p ON p.id=d.producto_id JOIN empleados e ON e.id=d.empleado_id WHERE d.fecha>=? AND d.fecha<? ORDER BY d.fecha,d.id LIMIT 20000",
        'facturas'=>"SELECT f.confirmado fecha,f.proveedor_nombre proveedor,f.numero,f.fecha_emision,f.total,em.nombre responsable FROM facturas f LEFT JOIN empleados em ON em.id=f.confirmado_por WHERE f.estado='confirmada' AND f.confirmado>=? AND f.confirmado<? ORDER BY f.confirmado,f.id LIMIT 20000",
        'movimientos'=>'SELECT m.fecha,m.tipo,COALESCE(i.nombre,p.nombre) articulo,m.origen,m.destino,m.cantidad,m.referencia_tipo,m.referencia_id,e.nombre responsable FROM movimientos m LEFT JOIN ingredientes i ON i.id=m.ingrediente_id LEFT JOIN productos p ON p.id=m.producto_id LEFT JOIN empleados e ON e.id=m.empleado_id WHERE m.fecha>=? AND m.fecha<? ORDER BY m.fecha,m.id LIMIT 20000',
    };
    $data=all($exportSql,[$since,$until]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mercopan-'.$mode.'-'.$since.'-'.$end.'.csv"');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
    if($data){fputcsv($out,array_keys($data[0]),';','"','');foreach($data as $row){
        // Neutralizar fórmula de Excel/Calc en datos de catálogo importados.
        $safe=array_map(function($v){$str=(string)($v??'');return preg_match('/^\s*[=+@-]/u',$str)?"'".$str:$str;},array_values($row));
        fputcsv($out,$safe,';','"','');
    }}else fputcsv($out,['Sin registros en el período'],';','"','');
    fclose($out);exit;
}
require __DIR__.'/../../app/admin_view.php';
