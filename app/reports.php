<?php
declare(strict_types=1);
function dateWindow(mixed $start,mixed $end):array{
    $s=safeDate($start);$e=safeDate($end);check($s<=$e,'La fecha inicial supera la final.');
    $days=(new DateTimeImmutable($s))->diff(new DateTimeImmutable($e))->days;
    check($days<=1095,'Consultá como máximo tres años por vez.');
    $until=(new DateTimeImmutable($e))->modify('+1 day')->format('Y-m-d');
    return [$s,$e,$until];
}
function periodMetrics(string $start,string $endExclusive):array{
    $sales=one('SELECT COUNT(*) n,COALESCE(SUM(ingreso),0) income,COALESCE(SUM(cantidad),0) units, SUM(CASE WHEN costo_unitario IS NULL THEN 1 ELSE 0 END) cost_unknown,COALESCE(SUM(CASE WHEN costo_unitario IS NULL THEN 0 ELSE cantidad*costo_unitario END),0) cost FROM ventas WHERE revertido IS NULL AND fecha>=? AND fecha<?',[$start,$endExclusive]);
    $purchase=one("SELECT COUNT(*) n,COALESCE(SUM(total),0) total FROM facturas WHERE estado='confirmada' AND confirmado>=? AND confirmado<?",[$start,$endExclusive]);
    $waste=one('SELECT COUNT(*) n,COALESCE(SUM(cantidad),0) units,COALESCE(SUM(costo_total),0) cost,SUM(CASE WHEN costo_total IS NULL THEN 1 ELSE 0 END) cost_unknown FROM descartes WHERE revertido IS NULL AND fecha>=? AND fecha<?',[$start,$endExclusive]);
    $prod=one('SELECT COUNT(*) n,COALESCE(SUM(cantidad),0) units,COALESCE(SUM(costo_total),0) cost,SUM(CASE WHEN costo_total IS NULL THEN 1 ELSE 0 END) cost_unknown FROM producciones WHERE fecha>=? AND fecha<?',[$start,$endExclusive]);
    $top=all('SELECT p.nombre,p.unidad,SUM(v.cantidad) qty,SUM(v.ingreso) revenue FROM ventas v JOIN productos p ON p.id=v.producto_id WHERE v.revertido IS NULL AND v.fecha>=? AND v.fecha<? GROUP BY p.id,p.nombre,p.unidad ORDER BY qty DESC LIMIT 10',[$start,$endExclusive]);
    $discardTop=all('SELECT p.nombre,p.unidad,SUM(d.cantidad) qty,COALESCE(SUM(d.costo_total),0) cost FROM descartes d JOIN productos p ON p.id=d.producto_id WHERE d.revertido IS NULL AND d.fecha>=? AND d.fecha<? GROUP BY p.id,p.nombre,p.unidad ORDER BY qty DESC LIMIT 10',[$start,$endExclusive]);
    $least=all('SELECT p.nombre,p.unidad,COALESCE(SUM(v.cantidad),0) qty FROM productos p LEFT JOIN ventas v ON v.producto_id=p.id AND v.revertido IS NULL AND v.fecha>=? AND v.fecha<? WHERE p.activo=1 GROUP BY p.id,p.nombre,p.unidad ORDER BY qty ASC,p.nombre LIMIT 10',[$start,$endExclusive]);
    $producedTop=all('SELECT p.nombre,p.unidad,SUM(pr.cantidad) qty FROM producciones pr JOIN productos p ON p.id=pr.producto_id WHERE pr.fecha>=? AND pr.fecha<? GROUP BY p.id,p.nombre,p.unidad ORDER BY qty DESC LIMIT 10',[$start,$endExclusive]);
    $purchaseDays=all("SELECT DATE(confirmado) day,ROUND(SUM(total),2) expense FROM facturas WHERE estado='confirmada' AND confirmado>=? AND confirmado<? GROUP BY DATE(confirmado) ORDER BY day",[$start,$endExclusive]);
    $wasteRatios=all('SELECT p.nombre,p.unidad,COALESCE(w.qty,0) discarded,COALESCE(v.qty,0) sold,ROUND(100*COALESCE(w.qty,0)/(COALESCE(w.qty,0)+COALESCE(v.qty,0)),1) percent FROM productos p LEFT JOIN (SELECT producto_id,SUM(cantidad) qty FROM descartes WHERE revertido IS NULL AND fecha>=? AND fecha<? GROUP BY producto_id) w ON w.producto_id=p.id LEFT JOIN (SELECT producto_id,SUM(cantidad) qty FROM ventas WHERE revertido IS NULL AND fecha>=? AND fecha<? GROUP BY producto_id) v ON v.producto_id=p.id WHERE (COALESCE(w.qty,0)+COALESCE(v.qty,0))>0 ORDER BY percent DESC,discarded DESC LIMIT 10',[$start,$endExclusive,$start,$endExclusive]);

    $days=all('SELECT DATE(fecha) day,ROUND(SUM(ingreso),2) revenue FROM ventas WHERE revertido IS NULL AND fecha>=? AND fecha<? GROUP BY DATE(fecha) ORDER BY day',[$start,$endExclusive]);
    $providers=all("SELECT proveedor_nombre,SUM(total) expense FROM facturas WHERE estado='confirmada' AND confirmado>=? AND confirmado<? GROUP BY proveedor_id,proveedor_nombre ORDER BY expense DESC LIMIT 10",[$start,$endExclusive]);
    $usage=all("SELECT i.nombre,i.unidad,SUM(c.cantidad) qty FROM consumos c JOIN producciones p ON p.id=c.produccion_id JOIN ingredientes i ON i.id=c.ingrediente_id WHERE p.fecha>=? AND p.fecha<? GROUP BY i.id,i.nombre,i.unidad ORDER BY qty DESC LIMIT 10",[$start,$endExclusive]);
    $byWeekday=all('SELECT DAYOFWEEK(fecha) wd,ROUND(SUM(ingreso),2) revenue FROM ventas WHERE revertido IS NULL AND fecha>=? AND fecha<? GROUP BY DAYOFWEEK(fecha) ORDER BY wd',[$start,$endExclusive]);
    $byHour=all('SELECT HOUR(fecha) h,ROUND(SUM(ingreso),2) revenue FROM ventas WHERE revertido IS NULL AND fecha>=? AND fecha<? GROUP BY HOUR(fecha) ORDER BY h',[$start,$endExclusive]);
    $stocks=one('SELECT COALESCE(SUM((stock_deposito+stock_produccion)*costo_promedio),0) total,SUM(CASE WHEN costo_promedio IS NULL AND (stock_deposito+stock_produccion)>0 THEN 1 ELSE 0 END) unknown FROM ingredientes');
    $finished=one('SELECT COALESCE(SUM(stock*costo_estimado),0) total,SUM(CASE WHEN costo_estimado IS NULL AND stock>0 THEN 1 ELSE 0 END) unknown FROM productos');
    $low=all('SELECT id,nombre,unidad,stock_deposito,minimo_deposito FROM ingredientes WHERE activo=1 AND stock_deposito<=minimo_deposito ORDER BY stock_deposito ASC LIMIT 20');
    return compact('sales','purchase','waste','prod','top','least','producedTop','purchaseDays','wasteRatios','discardTop','days','providers','usage','byWeekday','byHour','stocks','finished','low');
}
function insights(array $metrics,string $start,string $end):array{
    $days=(new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days+1;
    $notes=[];
    if($days<14) return ['Para recomendaciones de tendencia, elegí un período de al menos 14 días.'];
    $sales=(int)$metrics['sales']['n'];
    if($sales<10)return ['Todavía no hay suficientes ventas verificadas (mínimo 10 registros en el período) para analizar tendencias.'];
    $activeDays=count($metrics['days']);
    if($activeDays<7)return ['Aún faltan días con ventas registradas para comparar patrones semanales.'];
    $byHour=$metrics['byHour'];if(count($byHour)>=4){usort($byHour,fn($a,$b)=>(float)$b['revenue']<=>(float)$a['revenue']);$h=(int)$byHour[0]['h'];$notes[]='La franja horaria con más ingresos registrados comienza a las '.sprintf('%02d:00',$h).' (horario de registro, no necesariamente de compra real).';}
    if(count($metrics['top'])>=2){$best=$metrics['top'][0];$notes[]='El artículo con más cantidad vendida en el período es '.$best['nombre'].' ('.num($best['qty']).' '.$best['unidad'].').';}
    if($metrics['discardTop'] && (float)$metrics['waste']['units']>0){$worst=$metrics['discardTop'][0];$notes[]='Revisar el descarte de '.$worst['nombre'].' ('.num($worst['qty']).' '.$worst['unidad'].' en el período).';}
    if($metrics['wasteRatios'] && (float)$metrics['wasteRatios'][0]['percent']>=20.0 && (float)$metrics['wasteRatios'][0]['discarded']>=3) $notes[]='El porcentaje de descartes sobre salidas de '.$metrics['wasteRatios'][0]['nombre'].' es '.$metrics['wasteRatios'][0]['percent'].' %. Compará producción y demanda antes de ajustar la cantidad elaborada.';
    if($metrics['low'])$notes[]='Hay '.count($metrics['low']).' ingredientes en nivel mínimo o inferior (mostrando hasta 20).';
    return $notes?:['No se identificaron patrones robustos con los registros disponibles.'];
}
