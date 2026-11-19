<?php
declare(strict_types=1);
/** Cambios administrativos. No están disponibles desde las terminales. */
function saveCatalog(string $type,array $d): int {
    requireAdmin();
    $allowed=['empleados'=>['nombre','rol','activo'],
      'proveedores'=>['nombre','rut','telefono','activo'],
      'ingredientes'=>['nombre','unidad','categoria','icono','atajos','minimo_deposito','activo'],
      'productos'=>['nombre','categoria','unidad','icono','atajos','precio','activo']];
    check(isset($allowed[$type]),'Catálogo inválido.');
    if ($type!=='empleados' || (int)sql('SELECT COUNT(*) FROM empleados')->fetchColumn()>0) requireEmployee();
    $id=isset($d['id']) && (string)$d['id']!=='' ? recordId($d['id']):null;
    $name=textField($d['nombre']??null);
    $data=['nombre'=>$name];
    if($type==='empleados'){
        check(in_array($d['rol']??'operario',['operario','supervisor','administrador'],true),'Rol inválido.');
        $data['rol']=$d['rol']??'operario';
    } elseif($type==='proveedores'){
        $data['rut']=textField($d['rut']??'',24,false);
        $data['telefono']=textField($d['telefono']??'',60,false);
    } else {
        $units=$type==='ingredientes'?['kg','l','unidad']:['kg','unidad'];
        check(in_array($d['unidad']??'', $units,true),'Unidad inválida.');
        $data['unidad']=$d['unidad'];
        $data['categoria']=textField($d['categoria']??'General',80);
        $data['icono']=textField($d['icono']??'📦',12);
        $data['atajos']=textField($d['atajos']??'1,2,5',90);
        $parts=array_filter(array_map('trim',explode(',',$data['atajos'])),fn($s)=>$s!=='');
        check(count($parts)>=1 && count($parts)<=8,'Configurá entre 1 y 8 cantidades frecuentes.');
        foreach($parts as $s) positiveQty($s,$data['unidad']==='unidad');
        if($type==='ingredientes')$data['minimo_deposito']=nonnegativeQty($d['minimo_deposito']??'0');
        else $data['precio']=trim((string)($d['precio']??''))===''?null:amount($d['precio']);
    }
    $data['activo']=isset($d['activo']) && (string)$d['activo']==='1'?1:0;
    return trans(function()use($type,$data,$id){
        $before=$id?one("SELECT * FROM {$type} WHERE id=? FOR UPDATE",[$id]):null;
        if($id)check($before!==null,'Registro inexistente.');
        if($type==='ingredientes'||$type==='productos') {
            if($before && $before['unidad']!==$data['unidad']) {
                $quantity=$type==='ingredientes'?(float)$before['stock_deposito']+(float)$before['stock_produccion']:(float)$before['stock'];
                check($quantity<0.00001,'No podés cambiar la unidad mientras haya stock.');
                check($type!=='ingredientes'||one('SELECT id FROM receta_ingredientes WHERE ingrediente_id=? LIMIT 1',[$id])===null,'La unidad figura en recetas; corregí las recetas antes.');
            }
        }
        $fields=array_keys($data);
        if($id){$set=implode(',',array_map(fn($f)=>"`{$f}`=?",$fields));sql("UPDATE {$type} SET {$set} WHERE id=?",[...array_values($data),$id]);}
        else{$cols=implode(',',array_map(fn($f)=>"`{$f}`",$fields));$marks=implode(',',array_fill(0,count($fields),'?'));sql("INSERT INTO {$type} ({$cols}) VALUES ({$marks})",array_values($data));$id=(int)conn()->lastInsertId();}
        audit($before?'editar':'crear',$type,$id,$before??[],$data);
        return $id;
    });
}
function saveRecipe(array $d): int {
    requireAdmin();$actor=requireEmployee();
    $product=recordId($d['producto_id']??null);
    $yield=positiveQty($d['rendimiento']??null);
    $ids=$d['ingrediente_id']??[];$qty=$d['cantidad']??[];
    check(is_array($ids)&&is_array($qty),'Ingredientes inválidos.');
    $ingredients=[];
    foreach($ids as $i=>$raw){
        if(trim((string)$raw)==='')continue;
        $iid=recordId($raw);check(!isset($ingredients[$iid]),'No repitas ingredientes.');
        $ingredients[$iid]=positiveQty($qty[$i]??'');
    }
    check(count($ingredients)>0 && count($ingredients)<=60,'Se requiere al menos un ingrediente (máximo 60).');
    ksort($ingredients);
    return trans(function()use($product,$yield,$ingredients,$actor){
        $p=one('SELECT * FROM productos WHERE id=? FOR UPDATE',[$product]);check($p!==null,'Producto inexistente.');
        check($p['unidad']!=='unidad'||floor((float)$yield)===(float)$yield,'El rendimiento por unidad debe ser entero.');
        foreach($ingredients as $id=>$q){
            $ing=one('SELECT unidad,activo FROM ingredientes WHERE id=? FOR UPDATE',[$id]);check($ing!==null && $ing['activo'],'Ingrediente inexistente o inactivo.');
            check($ing['unidad']!=='unidad'||floor((float)$q)===(float)$q,'El ingrediente por unidad debe ser entero.');
        }
        $rec=one('SELECT id FROM recetas WHERE producto_id=? FOR UPDATE',[$product]);
        if($rec){$rid=(int)$rec['id'];sql('UPDATE recetas SET rendimiento=? WHERE id=?',[$yield,$rid]);sql('DELETE FROM receta_ingredientes WHERE receta_id=?',[$rid]);}
        else{sql('INSERT INTO recetas(producto_id,rendimiento) VALUES (?,?)',[$product,$yield]);$rid=(int)conn()->lastInsertId();}
        foreach($ingredients as $iid=>$q)sql('INSERT INTO receta_ingredientes(receta_id,ingrediente_id,cantidad) VALUES (?,?,?)',[$rid,$iid,$q]);
        audit('guardar_receta','recetas',$rid,[],['producto_id'=>$product,'rendimiento'=>$yield,'ingredientes'=>$ingredients]);
        return $rid;
    });
}
function createInvoiceUpload(array $file): int {
    requireAdmin();$emp=requireEmployee();
    $upload=uploadInvoice($file);
    $existing=one("SELECT id FROM facturas WHERE archivo_hash=? AND estado='confirmada' LIMIT 1",[$upload['hash']]);
    check($existing===null,'El archivo coincide con una factura ya confirmada (#'.$existing['id'].').');
    $guess=guessInvoice($upload['texto'],all('SELECT id,nombre FROM ingredientes WHERE activo=1'),all('SELECT id,nombre FROM proveedores WHERE activo=1'));
    return trans(function()use($emp,$upload,$guess){
        sql('INSERT INTO facturas(proveedor_id,proveedor_nombre,numero,fecha_emision,archivo,archivo_hash,ocr_texto,ocr_estado,ocr_alertas,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?)',
          [$guess['proveedor'],$guess['proveedor']?(one('SELECT nombre FROM proveedores WHERE id=?',[$guess['proveedor']])['nombre']):'',
          $guess['numero'],$guess['fecha_emision']!==''?$guess['fecha_emision']:null,
          $upload['archivo'],$upload['hash'],$upload['texto'],$upload['estado'],$upload['alertas'],$emp['id']]);
        $id=(int)conn()->lastInsertId();audit('subir_factura','facturas',$id,[],['estado'=>$upload['estado']]);return $id;
    });
}
function parseInvoiceLines(array $d): array {
    $ids=$d['ingrediente']??[];$q=$d['cantidad']??[];$cost=$d['costo_total']??[];
    check(is_array($ids)&&is_array($q)&&is_array($cost) && count($ids)<=70,'Renglones inválidos.');
    $out=[];
    foreach($ids as $i=>$raw){
        if(trim((string)$raw)==='')continue;
        $id=recordId($raw);check(!isset($out[$id]),'El ingrediente está repetido. Agrupá cantidades.');
        $out[$id]=['cantidad'=>positiveQty($q[$i]??null),'costo_total'=>amount($cost[$i]??null)];
    }
    check(count($out)<=50,'Máximo 50 ingredientes por factura.');ksort($out);return $out;
}
function saveInvoice(array $d): int {
    requireAdmin();$emp=requireEmployee();
    $id=isset($d['id'])&&$d['id']!==''?recordId($d['id']):null;
    $version=$id?recordId($d['version']??null):null;
    $supplier=isset($d['proveedor_id'])&&$d['proveedor_id']!==''?recordId($d['proveedor_id']):null;
    $number=textField($d['numero']??'',100,false);
    $date=trim((string)($d['fecha_emision']??''));if($date!=='')$date=safeDate($date);
    $lines=parseInvoiceLines($d);
    return trans(function()use($id,$version,$supplier,$number,$date,$lines,$emp){
        $before=$id?one('SELECT * FROM facturas WHERE id=? FOR UPDATE',[$id]):null;
        check(!$id || ($before!==null && $before['estado']==='borrador'),'La factura ya no se puede editar.');
        if ($id) check((int)$before['version']===$version,'La factura fue modificada en otra pantalla. Recargá antes de guardarla.');
        $sup=$supplier?one('SELECT * FROM proveedores WHERE id=? AND activo=1',[$supplier]):null;
        check($supplier===null || $sup!==null,'Proveedor inexistente o inactivo.');
        $total=0.;$prepared=[];
        foreach($lines as $iid=>$l){
            $ing=one('SELECT id,nombre,unidad,activo FROM ingredientes WHERE id=?',[$iid]);
            check($ing!==null && $ing['activo'],'Ingrediente inexistente/inactivo.');
            check($ing['unidad']!=='unidad'||floor((float)$l['cantidad'])===(float)$l['cantidad'],'Este ingrediente se cuenta en unidades enteras.');
            $prepared[]=[$iid,$ing['nombre'],$ing['unidad'],$l['cantidad'],$l['costo_total']];$total+=(float)$l['costo_total'];
        }
        check($total<=9999999999.99,'Total demasiado elevado.');
        if($id){sql('UPDATE facturas SET proveedor_id=?,proveedor_nombre=?,numero=?,fecha_emision=?,total=?,version=version+1 WHERE id=?',
            [$supplier,$sup['nombre']??'',$number,$date?:null,number_format($total,2,'.',''),$id]);
            sql('DELETE FROM factura_renglones WHERE factura_id=?',[$id]);}
        else{sql('INSERT INTO facturas(proveedor_id,proveedor_nombre,numero,fecha_emision,total,creado_por) VALUES (?,?,?,?,?,?)',
            [$supplier,$sup['nombre']??'',$number,$date?:null,number_format($total,2,'.',''),$emp['id']]);$id=(int)conn()->lastInsertId();}
        foreach($prepared as $r)sql('INSERT INTO factura_renglones(factura_id,ingrediente_id,ingrediente_nombre,unidad,cantidad,costo_total) VALUES (?,?,?,?,?,?)',[$id,...$r]);
        audit('guardar_borrador','facturas',$id,$before??[],['numero'=>$number,'total'=>$total,'items'=>count($lines)]);
        return $id;
    });
}
function confirmInvoice(mixed $raw,mixed $expectedVersion): array {
    requireAdmin();$emp=requireEmployee();$id=recordId($raw);$version=recordId($expectedVersion);
    return trans(function()use($id,$emp,$version){
        $invoice=one('SELECT * FROM facturas WHERE id=? FOR UPDATE',[$id]);
        check($invoice!==null && $invoice['estado']==='borrador','Factura inexistente o ya confirmada.');
        check((int)$invoice['version']===$version,'El borrador cambió. Recargá y volvé a revisar antes de confirmar.');
        check($invoice['proveedor_id']!==null && $invoice['numero']!=='' && $invoice['fecha_emision']!==null,'Completá proveedor, número y fecha.');
        $supplier=one('SELECT * FROM proveedores WHERE id=? AND activo=1',[$invoice['proveedor_id']]);check($supplier!==null,'El proveedor está inactivo.');
        $items=all('SELECT * FROM factura_renglones WHERE factura_id=? ORDER BY ingrediente_id',[$id]);check(count($items)>0,'Agregá al menos un ingrediente.');
        $dup=one("SELECT id FROM facturas WHERE proveedor_id=? AND numero_confirmado=? AND estado='confirmada' LIMIT 1",[$invoice['proveedor_id'],$invoice['numero']]);
        check($dup===null,'Ya está confirmada una factura del proveedor con ese número.');
        if($invoice['archivo_hash']!==null){
            $same=one("SELECT id FROM facturas WHERE archivo_hash=? AND estado='confirmada' AND id<>? LIMIT 1",[$invoice['archivo_hash'],$id]);
            check($same===null,'El mismo archivo ya fue confirmado en otra factura.');
        }
        foreach($items as $item){
            $i=one('SELECT * FROM ingredientes WHERE id=? FOR UPDATE',[$item['ingrediente_id']]);
            check($i!==null&&$i['activo'],'Revisá ingredientes: hay uno inactivo.');
            check($i['unidad']===$item['unidad'],'La unidad del ingrediente cambió; corregí el borrador.');
            $before=(float)$i['stock_deposito']+(float)$i['stock_produccion'];
            $cost=(float)$item['costo_total']/(float)$item['cantidad'];
            $avg=($before>0 && $i['costo_promedio']===null) ? null : (($before*(float)($i['costo_promedio']??0)+(float)$item['costo_total'])/($before+(float)$item['cantidad']));
            $avg=$avg===null?null:number_format($avg,4,'.','');
            sql('UPDATE ingredientes SET stock_deposito=stock_deposito+?,costo_promedio=? WHERE id=?',[$item['cantidad'],$avg,$i['id']]);
            movement('compra',(int)$i['id'],null,null,'deposito',$item['cantidad'],number_format($cost,4,'.',''),'factura',$id,$emp['id'],$invoice['numero']);
        }
        sql("UPDATE facturas SET estado='confirmada',numero_confirmado=numero,version=version+1,confirmado=NOW(),confirmado_por=? WHERE id=?",[$emp['id'],$id]);
        audit('confirmar_factura','facturas',$id,[],['total'=>$invoice['total'],'renglones'=>count($items)]);
        return ['mensaje'=>'Factura confirmada: se ingresaron '.count($items).' ingredientes al depósito.'];
    });
}
function cancelInvoice(mixed $raw): void {
    requireAdmin();requireEmployee();$id=recordId($raw);
    trans(function()use($id){
        $invoice=one('SELECT * FROM facturas WHERE id=? FOR UPDATE',[$id]);
        check($invoice!==null&&$invoice['estado']==='borrador','Solo se pueden cancelar borradores.');
        sql("UPDATE facturas SET estado='cancelada' WHERE id=?",[$id]);audit('cancelar_borrador','facturas',$id);
    });
}
