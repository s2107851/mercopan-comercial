<?php
declare(strict_types=1);
$validPages=['inicio','facturas','factura','proveedores','ingredientes','productos','recetas','produccion','stock','movimientos','ventas','descartes','empleados','reportes','configuracion'];
$page=in_array($_GET['page']??'inicio',$validPages,true)?(string)($_GET['page']??'inicio'):'inicio';
$pages=['inicio'=>'Panel de control','facturas'=>'Facturas y OCR','factura'=>'Revisión de factura','proveedores'=>'Proveedores','ingredientes'=>'Materias primas','productos'=>'Productos elaborados','recetas'=>'Recetas','produccion'=>'Producción','stock'=>'Stock e inventario','movimientos'=>'Trazabilidad','ventas'=>'Historial de ventas','descartes'=>'Historial de descartes','empleados'=>'Empleados','reportes'=>'Estadísticas y reportes','configuracion'=>'Configuración'];
$csrf=csrf();$msg=flash();$error=(string)($_SESSION['flash_error']??'');unset($_SESSION['flash_error']);
function hiddenAction(string $action,string $back=''):void{echo '<input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="'.e($action).'">';if($back!=='')echo '<input type="hidden" name="back" value="'.e($back).'">';}
function inputRow(string $name,string $label,mixed $value='',string $type='text',string $extras=''):void{
 echo '<div class="form-field"><label for="field_'.e($name).'">'.e($label).'</label><input id="field_'.e($name).'" name="'.e($name).'" type="'.e($type).'" value="'.e($value??'').'" '.$extras.'></div>';
}
function selectRow(string $name,string $label,array $values,mixed $selected=null):void{
 echo '<div class="form-field"><label for="field_'.e($name).'">'.e($label).'</label><select id="field_'.e($name).'" name="'.e($name).'">';
 foreach($values as $v=>$labelText) echo '<option value="'.e($v).'"'.((string)$selected===(string)$v?' selected':'').'>'.e($labelText).'</option>';
 echo '</select></div>';
}
function panelStart(string $heading):void{echo '<section class="panel"><h2>'.e($heading).'</h2>';}
function panelEnd():void{echo '</section>';}
function selectCatalog(string $name,array $options,mixed $selected=null,bool $blank=false):void {
 echo '<select name="'.e($name).'" aria-label="'.e($name).'">';if($blank)echo '<option value="">Seleccionar…</option>';
 echo selectOptions($options,$selected).'</select>';
}
function barChart(array $entries,string $label,string $value,string $format='money'):void{
 if(!$entries){echo '<p class="muted">Todavía no hay registros en el período.</p>';return;}
 $max=max(0.001,...array_map(fn($r)=>(float)$r[$value],$entries));
 echo '<div class="bar-stack">';foreach($entries as $r){$p=min(100,100*(float)$r[$value]/$max);
  echo '<div class="bar-row"><span>'.e((string)$r[$label]).'</span><div class="bar-track"><div class="bar-fill" style="width:'.round($p,2).'%"></div></div><strong class="right">'.($format==='money'?'$ '.money($r[$value]):num($r[$value])).'</strong></div>';
 }echo '</div>';
}
$employees=adminOK()?all('SELECT id,nombre FROM empleados WHERE activo=1 ORDER BY nombre'):[];
$emp=adminOK()?employee():null;
?><!doctype html><html lang="es-UY"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#062958"><title><?=e($pages[$page])?> · Mercopan</title><link rel="stylesheet" href="../assets/estilos.css"></head>
<body>
<?php if(!adminOK()):?>
<div class="login-wrap"><section class="panel login-card"><div class="logo"><span class="logo-mark">M</span><div><strong>MERCOPAN</strong><small>ADMINISTRACIÓN GENERAL</small></div></div><h1>Acceso administrativo</h1><p class="muted">Área restringida. Las terminales táctiles no requieren contraseña.</p>
<?php if($error!==''):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<form method="post"><?php hiddenAction('login');?><div class="form-field"><label for="password">Contraseña administrativa</label><input type="password" name="password" id="password" autocomplete="current-password" required></div><button class="btn-primary" type="submit">Ingresar</button></form></section></div>
<?php else: ?>
<div class="admin-shell"><aside class="admin-sidebar"><div class="logo"><span class="logo-mark">M</span><div><strong>MERCOPAN</strong><small>GESTIÓN COMERCIAL</small></div></div>
<div class="nav-label">GENERAL</div>
<?php foreach(['inicio','facturas','proveedores','ingredientes','productos','recetas','produccion','stock','movimientos','ventas','descartes','empleados','reportes','configuracion'] as $dest):?>
<a class="<?=($page===$dest || ($page==='factura'&&$dest==='facturas'))?'current':''?>" href="?page=<?=e($dest)?>"><?=e($pages[$dest])?></a>
<?php endforeach;?></aside><main class="admin-work"><div class="admin-toolbar"><div><div class="eyebrow">PANADERÍA · SALTO, URUGUAY</div><h1><?=e($pages[$page])?></h1></div><div class="admin-top-nav"><span class="pill">👤 <?=e($emp['nombre']??'Sin responsable')?></span>
<form method="post" style="display:flex;gap:6px;align-items:center"><?php hiddenAction('select_employee','?page='.$page);?>
<select name="id" aria-label="Cambiar responsable" required><option value="">Responsable</option><?=selectOptions($employees,$emp['id']??null)?></select><button class="btn-outline" type="submit">Elegir</button></form>
<form method="post"><?php hiddenAction('logout');?><button type="submit" class="btn-outline">Salir</button></form></div></div>
<?php if($msg!==''):?><div class="notice success" role="status"><?=e($msg)?></div><?php endif;?>
<?php if($error!==''):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if($emp===null):?><div class="notice">Seleccioná un responsable antes de registrar facturas, recetas, producción y movimientos. El nombre quedará en el historial.</div><?php endif;?>
<?php
switch($page){
case 'inicio':
 $m=periodMetrics(date('Y-m-01'),date('Y-m-d',strtotime('+1 day')));
 ?>
 <p class="muted">Datos de <?=e(date('d/m/Y',strtotime('first day of this month')))?> hasta hoy. Importes en pesos uruguayos.</p>
 <div class="stats-grid"><div class="stat"><small>Ingresos por ventas</small><b>$ <?=money($m['sales']['income'])?></b><small><?=e($m['sales']['n'])?> operaciones</small></div>
 <div class="stat"><small>Compras ingresadas</small><b>$ <?=money($m['purchase']['total'])?></b><small>Facturas confirmadas</small></div>
 <div class="stat"><small>Costos de producción</small><b>$ <?=money($m['prod']['cost'])?></b><small>Estimados por recetas y compras</small></div>
 <div class="stat"><small>Pérdidas estimadas</small><b>$ <?=money($m['waste']['cost'])?></b><small><?=e($m['waste']['n'])?> descartes</small></div>
 </div><div class="data-grid"><section class="panel"><h2>Ventas de este mes</h2><?php barChart(array_slice($m['days'],-14),'day','revenue');?></section>
 <section class="panel"><h2>Más vendidos</h2><?php barChart(array_slice($m['top'],0,7),'nombre','qty','qty');?></section></div>
 <?php panelStart('Stock bajo');if(!$m['low'])echo '<p>Sin alertas de stock mínimo.</p>';else{echo '<ul>';foreach($m['low'] as $l)echo '<li>'.e($l['nombre']).': '.num($l['stock_deposito']).' '.e($l['unidad']).' (mínimo '.num($l['minimo_deposito']).')</li>';echo '</ul>';}panelEnd();
 break;
case 'empleados':case 'proveedores':case 'ingredientes':case 'productos':
 $table=$page;$rows=all("SELECT * FROM {$table} ORDER BY nombre LIMIT 500");
 $edit=isset($_GET['edit'])?recordId($_GET['edit']):null;
 $row=$edit?one("SELECT * FROM {$table} WHERE id=?",[$edit]):null;
 panelStart($row?'Editar '.rtrim($pages[$page],'s').' #'.$row['id']:'Agregar a '.$pages[$page]);
 echo '<form method="post"><div class="form-grid">';hiddenAction('save_catalog','?page='.$page);
 echo '<input type="hidden" name="type" value="'.e($table).'">';if($row)echo '<input type="hidden" name="id" value="'.e($row['id']).'">';
 inputRow('nombre','Nombre',$row['nombre']??'','text','required maxlength="120"');
 if($table==='empleados')selectRow('rol','Rol',['operario'=>'Operario','supervisor'=>'Supervisor','administrador'=>'Administrador'],$row['rol']??'operario');
 if($table==='proveedores'){inputRow('rut','RUT',$row['rut']??'');inputRow('telefono','Teléfono',$row['telefono']??'');}
 if(in_array($table,['ingredientes','productos'],true)){
 selectRow('unidad','Unidad',$table==='ingredientes'?['kg'=>'Kilogramos','l'=>'Litros','unidad'=>'Unidades']:['kg'=>'Kilogramos','unidad'=>'Unidades'],$row['unidad']??'unidad');
 inputRow('categoria','Categoría',$row['categoria']??'General');inputRow('icono','Icono (emoji)',$row['icono']??'📦');
 inputRow('atajos','Cantidades rápidas (coma)',$row['atajos']??'1,2,5');
 if($table==='ingredientes')inputRow('minimo_deposito','Stock mínimo de depósito',$row['minimo_deposito']??'0','number','min="0" step="0.001"');
 else inputRow('precio','Precio de venta ($ UYU)',$row['precio']??'','number','min="0" step="0.01"');
 }
 selectRow('activo','Estado',['1'=>'Activo','0'=>'Inactivo'],$row['activo']??'1');echo '</div><div class="form-actions"><button class="btn-primary">'.($row?'Guardar cambios':'Crear registro').'</button>';if($row)echo '<a href="?page='.e($page).'">Cancelar edición</a>';echo '</div></form>';panelEnd();
 panelStart('Listado ('.count($rows).')');echo '<div class="table-scroll"><table><thead><tr><th>Nombre</th><th>Estado</th><th>Datos</th><th>Acción</th></tr></thead><tbody>';
 foreach($rows as $r){$details=match($table){'empleados'=>$r['rol'],'proveedores'=>$r['rut'].' · '.$r['telefono'],'ingredientes'=>$r['unidad'].' · dep. '.num($r['stock_deposito']).' · prod. '.num($r['stock_produccion']),'productos'=>$r['categoria'].' · stock '.num($r['stock']).' · '.($r['precio']===null?'sin precio':'$ '.money($r['precio'])),default=>''};
 echo '<tr><td>'.e($r['nombre']).'</td><td><span class="badge '.($r['activo']?'ok':'bad').'">'.($r['activo']?'Activo':'Inactivo').'</span></td><td>'.e($details).'</td><td><a href="?page='.e($page).'&edit='.e($r['id']).'">Editar</a></td></tr>';}
 echo '</tbody></table></div>';panelEnd();break;
case 'recetas':
 $products=all('SELECT id,nombre,unidad FROM productos WHERE activo=1 ORDER BY nombre');$ingredients=all('SELECT id,nombre,unidad FROM ingredientes WHERE activo=1 ORDER BY nombre');
 $selected=isset($_GET['producto'])?recordId($_GET['producto']):($products[0]['id']??null);
 $recipe=$selected?one('SELECT * FROM recetas WHERE producto_id=?',[$selected]):null;
 $parts=$recipe?all('SELECT * FROM receta_ingredientes WHERE receta_id=?',[$recipe['id']]):[];
 panelStart('Configurar receta');echo '<form method="get"><input type="hidden" name="page" value="recetas"><div class="form-field"><label>Producto</label>';selectCatalog('producto',$products,$selected);echo '</div><button class="btn-outline">Abrir receta</button></form>';
 if($selected){echo '<form method="post">';hiddenAction('save_recipe','?page=recetas');echo '<input type="hidden" name="producto_id" value="'.e($selected).'">';inputRow('rendimiento','Rendimiento por lote (en unidad del producto)',$recipe['rendimiento']??'1','number','required min="0.001" step="0.001"');
 echo '<div class="table-scroll"><table><thead><tr><th>Ingrediente</th><th>Cantidad por lote</th></tr></thead><tbody id="recipe-rows">';
 for($i=0;$i<max(5,count($parts));$i++){$part=$parts[$i]??null;echo '<tr><td>';selectCatalog('ingrediente_id[]',$ingredients,$part['ingrediente_id']??null,true);echo '</td><td><input name="cantidad[]" type="number" step="0.001" min="0.001" value="'.e($part['cantidad']??'').'"></td></tr>';}
 echo '</tbody></table></div><template id="recipe-template"><tr><td>';selectCatalog('ingrediente_id[]',$ingredients,null,true);echo '</td><td><input name="cantidad[]" type="number" step="0.001" min="0.001"></td></tr></template><div class="form-actions"><button type="button" class="btn-outline" data-add-rows="recipe">+ Ingrediente</button><button class="btn-primary">Guardar receta</button></div></form>';}
 panelEnd();break;
case 'produccion':
 $products=all('SELECT p.id,p.nombre,p.unidad,r.rendimiento FROM productos p JOIN recetas r ON r.producto_id=p.id WHERE p.activo=1 ORDER BY p.nombre');
 panelStart('Registrar elaboración');echo '<p class="muted">Consume materia prima del área de producción (NO del depósito). Añade productos elaborados al stock del mostrador.</p><form method="post">';hiddenAction('produce','?page=produccion');echo '<input type="hidden" name="clave" value="'.e(bin2hex(random_bytes(16))).'">';
 echo '<div class="form-grid"><div class="form-field"><label>Producto con receta</label>';selectCatalog('producto_id',$products,null);echo '</div>';inputRow('lotes','Cantidad de lotes','1','number','required min="1" max="1000" step="1"');
 echo '</div><div class="form-actions"><button class="btn-primary">Confirmar producción</button></div></form>';panelEnd();
 panelStart('Producciones recientes');$rows=all('SELECT pr.*,p.nombre,em.nombre responsable FROM producciones pr JOIN productos p ON p.id=pr.producto_id JOIN empleados em ON em.id=pr.empleado_id ORDER BY pr.id DESC LIMIT 70');
 echo '<div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Producto</th><th>Lotes</th><th>Elaborado</th><th>Costo</th><th>Responsable</th></tr></thead><tbody>';
 foreach($rows as $r)echo '<tr><td>'.e($r['fecha']).'</td><td>'.e($r['nombre']).'</td><td>'.e($r['lotes']).'</td><td>'.num($r['cantidad']).'</td><td>$ '.($r['costo_total']===null?'Sin datos':money($r['costo_total'])).'</td><td>'.e($r['responsable']).'</td></tr>';echo '</tbody></table></div>';panelEnd();break;
case 'stock':
 $ingredients=all('SELECT * FROM ingredientes ORDER BY nombre');$products=all('SELECT * FROM productos ORDER BY nombre');
 panelStart('Existencias de materia prima');echo '<div class="table-scroll"><table><thead><tr><th>Ingrediente</th><th>Depósito</th><th>Área de producción</th><th>Unidad</th><th>Alerta</th></tr></thead><tbody>';
 foreach($ingredients as $i)echo '<tr><td>'.e($i['nombre']).'</td><td>'.num($i['stock_deposito']).'</td><td>'.num($i['stock_produccion']).'</td><td>'.e($i['unidad']).'</td><td>'.((float)$i['stock_deposito']<=(float)$i['minimo_deposito']?'<span class="badge bad">Mínimo</span>':'<span class="badge ok">Normal</span>').'</td></tr>';
 echo '</tbody></table></div>';panelEnd();panelStart('Stock de productos terminados');echo '<div class="table-scroll"><table><thead><tr><th>Producto</th><th>Unidades disponibles</th><th>Costo unitario estimado</th></tr></thead><tbody>';
 foreach($products as $p)echo '<tr><td>'.e($p['nombre']).'</td><td>'.num($p['stock']).' '.e($p['unidad']).'</td><td>'.($p['costo_estimado']===null?'Sin datos':'$ '.money($p['costo_estimado'])).'</td></tr>';echo '</tbody></table></div>';panelEnd();
 panelStart('Ajustar inventario físico (con motivo)');echo '<p class="muted">Usá esta función después de contar existencias reales; todos los cambios quedan auditados.</p><div class="data-grid">';
 foreach(['deposito'=>'Depósito','produccion'=>'Área de producción','terminados'=>'Productos terminados'] as $type=>$label){echo '<form class="panel" method="post">';hiddenAction('adjust','?page=stock');echo '<h3>'.e($label).'</h3><input type="hidden" name="sector" value="'.e($type).'"><input type="hidden" name="clave" value="'.e(bin2hex(random_bytes(16))).'"><div class="form-field"><label>Artículo</label>';selectCatalog('id',$type==='terminados'?$products:$ingredients);echo '</div>';inputRow('nuevo_stock','Conteo físico nuevo','','number','required min="0" step="0.001"');inputRow('motivo','Motivo de ajuste','','text','required maxlength="200"');echo '<button class="btn-outline">Guardar ajuste</button></form>';}
 echo '</div>';panelEnd();break;
case 'facturas':
 panelStart('Digitalizar una factura de proveedor');echo '<p class="muted">Subí JPG, PNG o PDF (hasta 10 MB). El OCR intenta leer los datos, pero <strong>nada ingresa al stock sin revisión humana</strong>.</p>';
 echo '<form method="post" enctype="multipart/form-data">';hiddenAction('upload_invoice','?page=facturas');echo '<div class="upfile"><label for="upload-file">Documento digital o foto</label><input style="display:block;margin:12px 0" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" id="upload-file" name="archivo" required><button class="btn-primary">Leer y preparar borrador</button></div></form><p><a href="?page=factura">Crear factura manual sin archivo</a></p>';panelEnd();
 panelStart('Facturas y borradores');$rows=all('SELECT f.*,pr.nombre proveedor FROM facturas f LEFT JOIN proveedores pr ON pr.id=f.proveedor_id ORDER BY f.id DESC LIMIT 100');echo '<div class="table-scroll"><table><thead><tr><th>#</th><th>Estado</th><th>Emisión</th><th>Proveedor</th><th>Número</th><th>Total</th><th>Revisar</th></tr></thead><tbody>';
 foreach($rows as $r)echo '<tr><td>'.e($r['id']).'</td><td><span class="badge '.($r['estado']==='confirmada'?'ok':($r['estado']==='cancelada'?'bad':'')).'">'.e($r['estado']).'</span></td><td>'.e($r['fecha_emision']??'—').'</td><td>'.e($r['proveedor_nombre']?:'Sin proveedor').'</td><td>'.e($r['numero']?:'—').'</td><td>$ '.money($r['total']).'</td><td><a href="?page=factura&id='.e($r['id']).'">Abrir</a></td></tr>';
 echo '</tbody></table></div>';panelEnd();break;
case 'factura':
 $id=isset($_GET['id'])?recordId($_GET['id']):null;$invoice=$id?one('SELECT * FROM facturas WHERE id=?',[$id]):null;
 if($id && !$invoice){echo '<div class="notice error">Factura no encontrada.</div>';break;}
 $suppliers=all('SELECT id,nombre FROM proveedores WHERE activo=1 ORDER BY nombre');
 $ingredients=all('SELECT id,nombre,unidad FROM ingredientes WHERE activo=1 ORDER BY nombre');
 $lines=$id?all('SELECT * FROM factura_renglones WHERE factura_id=? ORDER BY id',[$id]):[];
 $hint=$invoice && !$lines?guessInvoice((string)$invoice['ocr_texto'],$ingredients,$suppliers):['lineas'=>[],'total_detectado'=>'','advertencias'=>[]];
 if($invoice){panelStart('Documento original y lectura OCR');echo '<p>Estado: <strong>'.e($invoice['estado']).'</strong> · OCR: '.e($invoice['ocr_estado']).'</p>';
 if($invoice['archivo'])echo '<p><a href="?archivo='.e($invoice['id']).'">Descargar documento original</a></p>';
 if($invoice['ocr_alertas'])echo '<div class="notice">'.e($invoice['ocr_alertas']).'</div>';
 if($hint['total_detectado']??'')echo '<p><strong>Total detectado (sin validar): </strong>'.e($hint['total_detectado']).'</p>';
 if($invoice['ocr_texto'])echo '<details><summary>Ver texto extraído por OCR</summary><pre class="ocr-raw">'.e($invoice['ocr_texto']).'</pre></details>';
 panelEnd();}
 panelStart($invoice?'Factura #'.$invoice['id']:'Crear factura manual');
 if(!$invoice || $invoice['estado']==='borrador'){
  echo '<p class="muted">Todos los renglones son editables; ninguna cantidad se descuenta o suma hasta <strong>Confirmar e ingresar stock</strong>.</p><form method="post">';hiddenAction('save_invoice','?page=factura'.($id?'&id='.$id:''));if($id)echo '<input type="hidden" name="id" value="'.e($id).'"><input type="hidden" name="version" value="'.e($invoice['version']).'">';
  echo '<div class="form-grid"><div class="form-field"><label>Proveedor</label>';selectCatalog('proveedor_id',$suppliers,$invoice['proveedor_id']??null,true);echo '</div>';
  inputRow('numero','Número de factura',$invoice['numero']??'','text','maxlength="100"');inputRow('fecha_emision','Fecha de emisión',$invoice['fecha_emision']??'','date');echo '</div>';
  echo '<h3>Productos e importes de la factura</h3><div class="table-scroll"><table><thead><tr><th>Ingrediente</th><th>Cantidad</th><th>Costo total ($ UYU)</th></tr></thead><tbody id="invoice-rows">';
  $shown=$lines;
  if(!$shown && isset($hint['lineas']))foreach($hint['lineas'] as $l)$shown[]=$l;
  for($i=0;$i<max(8,count($shown));$i++){
    $line=$shown[$i]??[];echo '<tr><td>';selectCatalog('ingrediente[]',$ingredients,$line['ingrediente_id']??null,true);echo '</td><td><input type="number" name="cantidad[]" step="0.001" min="0.001" value="'.e($line['cantidad']??'').'"></td><td><input type="number" name="costo_total[]" step="0.01" min="0" value="'.e($line['costo_total']??'').'"></td></tr>';
  }
  echo '</tbody></table></div><template id="invoice-template"><tr><td>';selectCatalog('ingrediente[]',$ingredients,null,true);echo '</td><td><input type="number" name="cantidad[]" step="0.001" min="0.001"></td><td><input type="number" name="costo_total[]" step="0.01" min="0"></td></tr></template><div class="form-actions"><button type="button" class="btn-outline" data-add-rows="invoice">+ Renglón</button><button class="btn-primary">Guardar borrador</button></div></form>';
  if($invoice){echo '<div class="form-actions"><form method="post" data-confirm="confirmar">';hiddenAction('confirm_invoice','?page=factura&id='.$id);echo '<input type="hidden" name="id" value="'.e($id).'"><input type="hidden" name="version" value="'.e($invoice['version']).'"><button type="submit" class="btn-primary">Confirmar e ingresar stock</button></form><form method="post" data-confirm="cancelar">';hiddenAction('cancel_invoice','?page=factura&id='.$id);echo '<input type="hidden" name="id" value="'.e($id).'"><button type="submit" class="btn-outline">Cancelar borrador</button></form></div>';}
 }else{echo '<div class="notice">El documento está cerrado. No se puede editar ni volver a ingresar la mercadería.</div><p><strong>Proveedor: </strong>'.e($invoice['proveedor_nombre']).' · <strong>N.º: </strong>'.e($invoice['numero']).' · <strong>Emisión: </strong>'.e($invoice['fecha_emision']).'</p><div class="table-scroll"><table><thead><tr><th>Ingrediente</th><th>Cantidad</th><th>Costo total</th></tr></thead><tbody>';foreach($lines as $line)echo '<tr><td>'.e($line['ingrediente_nombre']).'</td><td>'.num($line['cantidad']).' '.e($line['unidad']).'</td><td>$ '.money($line['costo_total']).'</td></tr>';echo '</tbody></table></div>';}
 if($invoice)echo '<h3>Total: $ '.money($invoice['total']).'</h3>';panelEnd();break;
case 'ventas':case 'descartes':
 $isSale=$page==='ventas';$table=$isSale?'ventas':'descartes';
 $rows=all("SELECT r.*,p.nombre producto,p.unidad,e.nombre responsable FROM {$table} r JOIN productos p ON p.id=r.producto_id JOIN empleados e ON e.id=r.empleado_id ORDER BY r.id DESC LIMIT 180");
 panelStart('Movimientos recientes');echo '<p class="muted">Una operación revertida se conserva, pero no se suma a las estadísticas.</p><div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Producto</th><th>Cantidad</th><th>'.($isSale?'Ingreso':'Motivo').'</th><th>Responsable</th><th>Estado</th></tr></thead><tbody>';
 foreach($rows as $r)echo '<tr><td>'.e($r['fecha']).'</td><td>'.e($r['producto']).'</td><td>'.num($r['cantidad']).' '.e($r['unidad']).'</td><td>'.($isSale?'$ '.money($r['ingreso']):e($r['motivo'])).'</td><td>'.e($r['responsable']).'</td><td>'.($r['revertido']?'Revertido':'Vigente').'</td></tr>';
 echo '</tbody></table></div>';panelEnd();break;
case 'movimientos':
 $rows=all('SELECT m.*,i.nombre ingrediente,p.nombre producto,emp.nombre responsable FROM movimientos m LEFT JOIN ingredientes i ON i.id=m.ingrediente_id LEFT JOIN productos p ON p.id=m.producto_id LEFT JOIN empleados emp ON emp.id=m.empleado_id ORDER BY m.id DESC LIMIT 300');
 panelStart('Historial auditable de movimientos');echo '<p class="muted">Muestra los últimos 300 movimientos. Ningún cambio se elimina físicamente.</p><div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Artículo</th><th>Ruta</th><th>Cantidad</th><th>Empleado</th><th>Referencia</th></tr></thead><tbody>';
 foreach($rows as $r)echo '<tr><td>'.e($r['fecha']).'</td><td>'.e($r['tipo']).'</td><td>'.e($r['ingrediente']??$r['producto']??'—').'</td><td>'.e(($r['origen']??'Entrada').' → '.($r['destino']??'Salida')).'</td><td>'.num($r['cantidad']).'</td><td>'.e($r['responsable']??'—').'</td><td>'.e($r['referencia_tipo']).' #'.e($r['referencia_id']).'</td></tr>';
 echo '</tbody></table></div>';panelEnd();
 panelStart('Retiros de depósito');$retiros=all('SELECT r.*,i.nombre ingrediente,e.nombre responsable FROM retiros r JOIN ingredientes i ON i.id=r.ingrediente_id JOIN empleados e ON e.id=r.empleado_id ORDER BY r.id DESC LIMIT 80');
 echo '<div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Ingrediente</th><th>Cantidad</th><th>Responsable</th><th>Estado</th></tr></thead><tbody>';foreach($retiros as $r)echo '<tr><td>'.e($r['fecha']).'</td><td>'.e($r['ingrediente']).'</td><td>'.num($r['cantidad']).'</td><td>'.e($r['responsable']).'</td><td>'.($r['revertido']?'Corregido':'Vigente').'</td></tr>';echo '</tbody></table></div>';panelEnd();break;
case 'reportes':
 $start=$_GET['desde']??date('Y-m-01');$end=$_GET['hasta']??date('Y-m-d');
 try{[$start,$end,$exclusive]=dateWindow($start,$end);$m=periodMetrics($start,$exclusive);}catch(DomainException $x){echo '<div class="notice error">'.e($x->getMessage()).'</div>';break;}
 panelStart('Filtros');echo '<form method="get" class="form-grid"><input type="hidden" name="page" value="reportes">';inputRow('desde','Desde',$start,'date','required');inputRow('hasta','Hasta',$end,'date','required');echo '<div class="form-field" style="justify-content:flex-end"><button class="btn-primary">Actualizar estadísticas</button></div></form>';panelEnd();
 echo '<div class="form-actions" style="margin:0 0 22px"><strong>Exportar CSV:</strong>';
 foreach(['ventas'=>'Ventas','descartes'=>'Descartes','facturas'=>'Facturas','movimientos'=>'Movimientos'] as $kind=>$label)echo '<a class="btn-outline" href="?export='.e($kind).'&desde='.e($start).'&hasta='.e($end).'">'.e($label).'</a>';
 echo '</div>';
 ?><div class="stats-grid"><div class="stat"><small>Ingresos registrados</small><b>$ <?=money($m['sales']['income'])?></b></div><div class="stat"><small>Compras materia prima</small><b>$ <?=money($m['purchase']['total'])?></b></div><div class="stat"><small>Producción estimada</small><b>$ <?=money($m['prod']['cost'])?></b></div><div class="stat"><small>Desperdicio estimado</small><b>$ <?=money($m['waste']['cost'])?></b></div><div class="stat"><small>Inventario actual (valor parcial)</small><b>$ <?=money((float)$m['stocks']['total']+(float)$m['finished']['total'])?></b></div>
 <?php if((int)$m['sales']['cost_unknown']===0 && (int)$m['sales']['n']>0):?><div class="stat"><small>Margen bruto estimado</small><b>$ <?=money((float)$m['sales']['income']-(float)$m['sales']['cost'])?></b><small>Sin mano de obra ni gastos generales</small></div><?php endif;?></div>
 <?php if((int)$m['sales']['cost_unknown']>0 || (int)$m['waste']['cost_unknown']>0 || (int)$m['prod']['cost_unknown']>0 || (int)$m['stocks']['unknown']>0 || (int)$m['finished']['unknown']>0):?><div class="notice">Algunos movimientos no tienen costo de receta confiable; los costos y pérdidas son parciales. No se informa una rentabilidad definitiva.</div><?php endif;?>
 <div class="data-grid"><section class="panel"><h2>Ingresos diarios</h2><?php barChart($m['days'],'day','revenue');?></section><section class="panel"><h2>Más vendidos (cantidad)</h2><?php barChart($m['top'],'nombre','qty','qty');?></section><section class="panel"><h2>Descartes por artículo</h2><?php barChart($m['discardTop'],'nombre','qty','qty');?></section><section class="panel"><h2>Gasto por proveedor</h2><?php barChart($m['providers'],'proveedor_nombre','expense');?></section><section class="panel"><h2>Consumo de ingredientes</h2><?php barChart($m['usage'],'nombre','qty','qty');?></section><section class="panel"><h2>Horarios de venta</h2><?php barChart(array_map(fn($r)=>['hora'=>sprintf('%02d:00',$r['h']),'revenue'=>$r['revenue']],$m['byHour']),'hora','revenue');?></section>
 <section class="panel"><h2>Menos vendidos (incluyendo cero)</h2><?php barChart($m['least'],'nombre','qty','qty');?></section>
 <section class="panel"><h2>Más elaborados</h2><?php barChart($m['producedTop'],'nombre','qty','qty');?></section>
 <section class="panel"><h2>Compras por día</h2><?php barChart($m['purchaseDays'],'day','expense');?></section>
 <section class="panel"><h2>Descarte sobre salidas (%)</h2><p class="subtitle">Cantidad descartada / (cantidad vendida + cantidad descartada). No es porcentaje sobre producción.</p><?php barChart($m['wasteRatios'],'nombre','percent','qty');?></section>
 <section class="panel"><h2>Ventas por día de semana</h2><?php $weekdayNames=[1=>'Domingo',2=>'Lunes',3=>'Martes',4=>'Miércoles',5=>'Jueves',6=>'Viernes',7=>'Sábado'];barChart(array_map(fn($r)=>['dia'=>$weekdayNames[(int)$r['wd']]??'?','revenue'=>$r['revenue']],$m['byWeekday']),'dia','revenue');?></section></div>
 <?php panelStart('Observaciones basadas en datos');echo '<ul>';foreach(insights($m,$start,$end) as $note)echo '<li>'.e($note).'</li>';echo '</ul><p class="subtitle">Los indicadores se derivan exclusivamente de movimientos registrados. No sustituyen la contabilidad ni representan facturación fiscal.</p>';panelEnd();break;
case 'configuracion':
 panelStart('Información de la instalación');echo '<p><strong>Moneda:</strong> pesos uruguayos · <strong>Zona horaria:</strong> America/Montevideo</p><p><strong>Depósito:</strong> /deposito/ · <strong>Mostrador:</strong> /mostrador/ · <strong>Administración:</strong> /admin/</p><p>Las interfaces operativas no tienen enlaces entre sí. Configurá en cada pantalla la URL de su sector.</p><p><strong>OCR:</strong> Tesseract local / PDF Poppler, sujeto a la instalación del servidor.</p><p><strong>Base:</strong> MySQL (inventario compartido, transacciones y movimientos trazables).</p><p>Administración protegida por contraseña y por reglas de acceso a la red. No exponer directamente el servidor a Internet.</p><p>Para respaldos, restauraciones y puesta en producción, seguí <code>docs/INSTALACION.md</code>.</p>';panelEnd();break;
}
?>
</main></div><?php endif;?>
<script src="../assets/admin.js" defer></script></body></html>
