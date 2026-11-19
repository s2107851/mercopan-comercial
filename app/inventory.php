<?php
declare(strict_types=1);
/** Una sola fuente de movimientos. Cada cambio de stock y su registro se efectúan en la misma transacción. */
function movement(string $type, ?int $ingredient, ?int $product, ?string $origin, ?string $dest,
                  string $quantity,?string $cost,string $refType,int $refId,int $actor,string $detail=''):void {
    sql('INSERT INTO movimientos (tipo,ingrediente_id,producto_id,origen,destino,cantidad,costo_unitario,referencia_tipo,referencia_id,empleado_id,detalle) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
       [$type,$ingredient,$product,$origin,$dest,$quantity,$cost,$refType,$refId,$actor,$detail]);
}
function withdrawIngredient(mixed $id,mixed $quantity,mixed $key): array {
    $actor=requireEmployee();$iid=recordId($id);
    return trans(function()use($actor,$iid,$quantity,$key){
        actionKey($key,'retiro',$actor['id']);
        $i=one('SELECT * FROM ingredientes WHERE id=? AND activo=1 FOR UPDATE',[$iid]);
        check($i!==null,'Ingrediente inactivo o inexistente.');
        $q=positiveQty($quantity,$i['unidad']==='unidad');
        check((float)$i['stock_deposito']+0.000001>=(float)$q,'No alcanza el stock del depósito.');
        sql('UPDATE ingredientes SET stock_deposito=stock_deposito-?, stock_produccion=stock_produccion+? WHERE id=?',[$q,$q,$iid]);
        sql('INSERT INTO retiros(ingrediente_id,cantidad,empleado_id) VALUES (?,?,?)',[$iid,$q,$actor['id']]);
        $rid=(int)conn()->lastInsertId();
        movement('retiro',$iid,null,'deposito','produccion',$q,$i['costo_promedio'],'retiro',$rid,$actor['id']);
        audit('retiro','retiros',$rid,[],['ingrediente'=>$iid,'cantidad'=>$q]);
        return ['id'=>$rid,'mensaje'=>"Retiro registrado: {$i['nombre']} (".num($q)." {$i['unidad']})"];
    });
}
function undoWithdraw(mixed $key): array {
    $actor=requireEmployee();
    return trans(function()use($actor,$key){
        actionKey($key,'revertir_retiro',$actor['id']);
        $r=one('SELECT r.*,i.nombre,i.unidad FROM retiros r JOIN ingredientes i ON i.id=r.ingrediente_id WHERE r.empleado_id=? AND r.revertido IS NULL ORDER BY r.id DESC LIMIT 1 FOR UPDATE',[$actor['id']]);
        check($r!==null,'No hay retiros recientes para corregir.');
        check(strtotime((string)$r['fecha'])>=time()-300,'La corrección rápida vence a los 5 minutos. Solicitá un ajuste administrativo.');
        $i=one('SELECT * FROM ingredientes WHERE id=? FOR UPDATE',[$r['ingrediente_id']]);
        check((float)$i['stock_produccion']+0.000001>=(float)$r['cantidad'],'Los ingredientes ya fueron utilizados; solicitá un ajuste administrativo.');
        sql('UPDATE ingredientes SET stock_produccion=stock_produccion-?,stock_deposito=stock_deposito+? WHERE id=?',[$r['cantidad'],$r['cantidad'],$r['ingrediente_id']]);
        sql('UPDATE retiros SET revertido=NOW(), revertido_por=? WHERE id=? AND revertido IS NULL',[$actor['id'],$r['id']]);
        movement('reversion_retiro',(int)$r['ingrediente_id'],null,'produccion','deposito',$r['cantidad'],$i['costo_promedio'],'retiro',(int)$r['id'],$actor['id'],'Corrección rápida');
        audit('revertir_retiro','retiros',(int)$r['id']);
        return ['mensaje'=>'Retiro corregido. Se conservó el historial.'];
    });
}
function sellProduct(mixed $id,mixed $quantity,mixed $key): array {
    $actor=requireEmployee();$pid=recordId($id);
    return trans(function()use($actor,$pid,$quantity,$key){
        actionKey($key,'venta',$actor['id']);
        $p=one('SELECT * FROM productos WHERE id=? AND activo=1 FOR UPDATE',[$pid]);
        check($p!==null,'Producto inactivo o inexistente.');
        $q=positiveQty($quantity,$p['unidad']==='unidad');
        check($p['precio']!==null,'Configurá el precio en Administración antes de registrar ventas.');
        check((float)$p['stock']+0.000001>=(float)$q,'No hay suficientes productos terminados.');
        $income=number_format(round((float)$q*(float)$p['precio'],2),2,'.','');
        sql('INSERT INTO ventas(producto_id,cantidad,precio_unitario,ingreso,costo_unitario,empleado_id) VALUES (?,?,?,?,?,?)',
            [$pid,$q,$p['precio'],$income,$p['costo_estimado'],$actor['id']]);
        $sid=(int)conn()->lastInsertId();
        sql('UPDATE productos SET stock=stock-? WHERE id=?',[$q,$pid]);
        movement('venta',null,$pid,'terminados',null,$q,$p['costo_estimado'],'venta',$sid,$actor['id']);
        audit('venta','ventas',$sid,[],['cantidad'=>$q,'ingreso'=>$income]);
        return ['id'=>$sid,'mensaje'=>'Venta registrada · $ '.money($income)];
    });
}
function discardProduct(mixed $id,mixed $quantity,mixed $reason,mixed $key): array {
    $actor=requireEmployee();$pid=recordId($id);
    $reasons=['vencido','sin_vender','error_elaboracion','quemado','danado','otro'];
    check(is_string($reason) && in_array($reason,$reasons,true),'Motivo de descarte inválido.');
    return trans(function()use($actor,$pid,$quantity,$reason,$key){
        actionKey($key,'descarte',$actor['id']);
        $p=one('SELECT * FROM productos WHERE id=? AND activo=1 FOR UPDATE',[$pid]);
        check($p!==null,'Producto inactivo o inexistente.');
        $q=positiveQty($quantity,$p['unidad']==='unidad');
        check((float)$p['stock']+0.000001>=(float)$q,'Stock terminado insuficiente.');
        $cost=$p['costo_estimado']===null?null:number_format(round((float)$q*(float)$p['costo_estimado'],2),2,'.','');
        sql('INSERT INTO descartes(producto_id,cantidad,motivo,costo_unitario,costo_total,empleado_id) VALUES (?,?,?,?,?,?)',
           [$pid,$q,$reason,$p['costo_estimado'],$cost,$actor['id']]);
        $did=(int)conn()->lastInsertId();
        sql('UPDATE productos SET stock=stock-? WHERE id=?',[$q,$pid]);
        movement('descarte',null,$pid,'terminados',null,$q,$p['costo_estimado'],'descarte',$did,$actor['id'],$reason);
        audit('descarte','descartes',$did,[],['cantidad'=>$q,'motivo'=>$reason]);
        return ['id'=>$did,'mensaje'=>'Descarte registrado; no genera ingresos.'];
    });
}
function undoLastCounter(string $kind,mixed $key): array {
    check(in_array($kind,['venta','descarte'],true),'Corrección inválida.');
    $actor=requireEmployee();
    $table=$kind==='venta'?'ventas':'descartes';
    return trans(function()use($actor,$key,$kind,$table){
        actionKey($key,'reversion_'.$kind,$actor['id']);
        $r=one("SELECT * FROM {$table} WHERE empleado_id=? AND revertido IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE",[$actor['id']]);
        check($r!==null,'No existe un registro para corregir.');
        check(strtotime((string)$r['fecha'])>=time()-300,'La corrección rápida venció. Solicitá ayuda a administración.');
        $p=one('SELECT * FROM productos WHERE id=? FOR UPDATE',[$r['producto_id']]);
        check($p!==null,'Producto no encontrado.');
        sql('UPDATE productos SET stock=stock+? WHERE id=?',[$r['cantidad'],$r['producto_id']]);
        sql("UPDATE {$table} SET revertido=NOW(),revertido_por=? WHERE id=? AND revertido IS NULL",[$actor['id'],$r['id']]);
        movement('reversion_'.$kind,null,(int)$r['producto_id'],null,'terminados',$r['cantidad'],$r['costo_unitario'],$kind,(int)$r['id'],$actor['id']);
        audit('reversion_'.$kind,$table,(int)$r['id']);
        return ['mensaje'=>'Movimiento corregido. Quedó registrado en el historial.'];
    });
}
function produce(mixed $id,mixed $batches,mixed $key): array {
    $actor=requireEmployee();$pid=recordId($id);$b=recordId($batches);
    check($b<=1000,'Máximo 1000 lotes por registro.');
    return trans(function()use($actor,$pid,$b,$key){
        actionKey($key,'produccion',$actor['id']);
        $p=one('SELECT * FROM productos WHERE id=? AND activo=1 FOR UPDATE',[$pid]);
        check($p!==null,'Producto inexistente o inactivo.');
        $rec=one('SELECT * FROM recetas WHERE producto_id=?',[$pid]);
        check($rec!==null,'Este producto no tiene receta.');
        $parts=all('SELECT ri.*,i.nombre,i.unidad FROM receta_ingredientes ri JOIN ingredientes i ON i.id=ri.ingrediente_id WHERE ri.receta_id=? ORDER BY ri.ingrediente_id',[$rec['id']]);
        check(count($parts)>0,'La receta no tiene ingredientes.');
        $q=positiveQty((float)$rec['rendimiento']*$b,$p['unidad']==='unidad');
        $used=[];$totalCost=0.;$missingCost=false;
        foreach($parts as $part){
            $i=one('SELECT * FROM ingredientes WHERE id=? FOR UPDATE',[$part['ingrediente_id']]);
            check($i!==null && (int)$i['activo']===1,'La receta contiene un ingrediente inactivo.');
            $needed=positiveQty((float)$part['cantidad']*$b,$i['unidad']==='unidad');
            check((float)$i['stock_produccion']+0.000001>=(float)$needed,'Falta '. $i['nombre'].' en el área de producción: se requieren '.num($needed).' '.$i['unidad'].'. Retirá primero del depósito.');
            if ($i['costo_promedio']===null) $missingCost=true;
            else $totalCost+=(float)$needed*(float)$i['costo_promedio'];
            $used[]=[$i,$needed];
        }
        $totalCost=$missingCost?null:round($totalCost,2);
        $unitCost=$totalCost===null?null:$totalCost/(float)$q;
        $oldStock=(float)$p['stock'];
        $newAvg=($unitCost===null || ($oldStock>0 && $p['costo_estimado']===null))
            ? null : (($oldStock>0) ? (($oldStock*(float)$p['costo_estimado'])+$totalCost)/($oldStock+(float)$q) : $unitCost);
        $newAvg=$newAvg===null?null:number_format($newAvg,4,'.','');
        sql('INSERT INTO producciones(producto_id,lotes,cantidad,costo_total,costo_unitario,empleado_id) VALUES (?,?,?,?,?,?)',
             [$pid,$b,$q,$totalCost===null?null:number_format($totalCost,2,'.',''),$unitCost===null?null:number_format($unitCost,4,'.',''),$actor['id']]);
        $prod=(int)conn()->lastInsertId();
        foreach($used as [$i,$needed]){
            sql('UPDATE ingredientes SET stock_produccion=stock_produccion-? WHERE id=?',[$needed,$i['id']]);
            sql('INSERT INTO consumos(produccion_id,ingrediente_id,cantidad,costo_unitario) VALUES (?,?,?,?)',[$prod,$i['id'],$needed,$i['costo_promedio']]);
            movement('consumo',(int)$i['id'],null,'produccion',null,$needed,$i['costo_promedio'],'produccion',$prod,$actor['id']);
        }
        sql('UPDATE productos SET stock=stock+?,costo_estimado=? WHERE id=?',[$q,$newAvg,$pid]);
        movement('elaboracion',null,$pid,null,'terminados',$q,$unitCost===null?null:number_format($unitCost,4,'.',''),'produccion',$prod,$actor['id']);
        audit('produccion','producciones',$prod,[],['producto'=>$pid,'cantidad'=>$q,'costo'=>$totalCost]);
        return ['mensaje'=>'Producción registrada: '.num($q).' '.$p['unidad'].' de '.$p['nombre']];
    });
}
/** Cambiar stock únicamente con motivo, auditoría y un movimiento de ajuste trazable. */
function adjustInventory(string $kind,mixed $id,mixed $newQty,string $reason,mixed $key): array {
    requireAdmin();$actor=requireEmployee();
    check(in_array($kind,['deposito','produccion','terminados'],true),'Ubicación inválida.');
    $rid=recordId($id);$reason=textField($reason,200);
    return trans(function()use($kind,$rid,$newQty,$reason,$key,$actor){
        actionKey($key,'ajuste',$actor['id']);
        $table=$kind==='terminados'?'productos':'ingredientes';
        $field=match($kind){'deposito'=>'stock_deposito','produccion'=>'stock_produccion',default=>'stock'};
        $r=one("SELECT * FROM {$table} WHERE id=? FOR UPDATE",[$rid]);check($r!==null,'Artículo inexistente.');
        $q=nonnegativeQty($newQty);
        check($r['unidad']!=='unidad' || floor((float)$q)===(float)$q,'El stock por unidades debe ser entero.');
        $old=(float)$r[$field];$delta=round((float)$q-$old,3);
        check(abs($delta)>0.000001,'El conteo coincide con el stock registrado.');
        sql("UPDATE {$table} SET {$field}=? WHERE id=?",[$q,$rid]);
        $ingredient=$kind==='terminados'?null:$rid;$product=$kind==='terminados'?$rid:null;
        movement('ajuste',$ingredient,$product,$delta>0?null:$kind,$delta>0?$kind:null,number_format(abs($delta),3,'.',''),null,'ajuste',0,$actor['id'],$reason);
        $last=(int)conn()->lastInsertId();
        sql("UPDATE movimientos SET referencia_id=? WHERE id=?",[$last,$last]);
        audit('ajuste',$table,$rid,[$field=>$r[$field]],[$field=>$q,'motivo'=>$reason]);
        return ['mensaje'=>'Stock ajustado con historial.'];
    });
}
