<?php
declare(strict_types=1);
// Pruebas sin base de datos para ejecutar en cualquier instalación PHP 8.1+.
require __DIR__.'/../app/core.php';
require __DIR__.'/../app/ocr.php';
function eq(mixed $actual,mixed $expected,string $test):void {
 if($actual!==$expected)throw new RuntimeException($test.' falló: esperado '.var_export($expected,true).' recibido '.var_export($actual,true));
 echo "OK: {$test}\n";
}
function expectReject(callable $fn,string $test):void{
 try{$fn();}catch(DomainException $e){echo "OK: {$test}\n";return;}
 throw new RuntimeException($test.' debería haber fallado');
}
eq(positiveQty('1,250'),'1.250','cantidad coma decimal');
eq(positiveQty('30',true),'30.000','unidad entera');
expectReject(fn()=>positiveQty('-5'),'cantidad negativa');
expectReject(fn()=>positiveQty('1.5',true),'unidad fraccionaria');
expectReject(fn()=>positiveQty('1.1234'),'más de tres decimales');
expectReject(fn()=>recordId('1 OR 1=1'),'id SQL malicioso');
eq(amount('1234,50'),'1234.50','importe decimal');
eq(safeDate('2026-11-18'),'2026-11-18','fecha válida');
expectReject(fn()=>safeDate('2026-02-30'),'fecha imposible');
$invoice="Factura A-000144\nAlmacén Test SA\nFecha 18/11/2026\nHarina 25 kg $ 1250,00\nHuevos 30 unidades $ 450,00\nImporte total: $ 1700,00";
$r=guessInvoice($invoice,[['id'=>1,'nombre'=>'Harina'],['id'=>2,'nombre'=>'Huevos']],[['id'=>4,'nombre'=>'Almacén Test SA']]);
eq((int)$r['proveedor'],4,'proveedor OCR sugerido');eq($r['fecha_emision'],'2026-11-18','fecha OCR sugerida');
eq(count($r['lineas']),2,'dos ingredientes OCR sugeridos');eq($r['lineas'][0]['costo_total'],'1250.00','importe OCR sugerido');
echo "Todas las pruebas unitarias sin DB pasaron.\n";
