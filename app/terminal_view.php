<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
check(isset($sector)&&in_array($sector,['deposito','mostrador'],true),'Terminal inválida.');
$people=all('SELECT id,nombre FROM empleados WHERE activo=1 ORDER BY nombre');
$emp=employee();
$title=$sector==='deposito'?'Retiro de depósito':'Ventas y descartes';
?>
<!doctype html><html lang="es-UY"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5">
<meta name="theme-color" content="#07346f"><title><?=e($title)?> · Mercopan</title><link rel="stylesheet" href="../assets/estilos.css"></head>
<body class="terminal" data-sector="<?=e($sector)?>" data-csrf="<?=e(csrf())?>">
<header class="terminal-header"><div class="logo"><span class="logo-mark">M</span><div><strong>MERCOPAN</strong><small><?=e($title)?></small></div></div>
<button class="persona-btn" id="change-person" type="button" aria-haspopup="dialog"><span>👤</span> <span id="persona-label"><?=e($emp['nombre']??'Elegir responsable')?></span> <span>⌄</span></button></header>
<main class="terminal-main"><div class="terminal-top"><div><div class="eyebrow">REGISTRO INTERNO</div><h1><?= $sector==='deposito'?'¿Qué ingrediente retirás?':'¿Qué producto registrás?' ?></h1><p class="muted">Elegí un producto, una cantidad y confirmá.</p></div>
<?php if($sector==='mostrador'):?><div class="seg-tabs" role="group" aria-label="Tipo de movimiento"><button class="tab active" type="button" data-mode="vender">Venta</button><button class="tab" type="button" data-mode="descartar">Descarte</button></div><?php endif;?></div>
<div class="search-row"><input id="filter" aria-label="Buscar producto" type="search" placeholder="Buscar por nombre..." autocomplete="off"><span id="item-count" class="tag"></span></div>
<div id="catalog" class="touch-grid"><div class="loading">Cargando productos…</div></div>
<div id="empty" class="empty hidden">No hay productos configurados en esta categoría.</div>
</main>
<div class="terminal-bottom"><span class="muted">Los movimientos se sincronizan al confirmarse.</span><button id="undo-last" class="btn-outline" type="button">↶ Corregir último movimiento</button></div>
<dialog id="person-dialog" class="modal"><form method="dialog"><div class="modal-head"><h2>¿Quién está trabajando?</h2><button class="close" value="cancel" aria-label="Cerrar">×</button></div><p class="muted">La selección identifica al responsable declarado; no verifica su identidad.</p><div class="people-grid">
<?php foreach($people as $p):?><button class="person-option" type="button" data-employee="<?=e($p['id'])?>"><?=e($p['nombre'])?></button><?php endforeach;?></div></form></dialog>
<dialog id="quantity-dialog" class="modal quantity-modal"><form method="dialog"><div class="modal-head"><h2 id="dialog-title">Cantidad</h2><button class="close" value="cancel" aria-label="Cerrar">×</button></div><div id="chosen-icon" class="chosen-icon">📦</div><p class="muted" id="stock-info"></p>
<div class="eyebrow">CANTIDADES FRECUENTES</div><div id="quick-amounts" class="qty-grid"></div><details class="custom-details"><summary>Otra cantidad</summary><label for="custom-amount">Cantidad personalizada</label><input id="custom-amount" type="number" min="0.001" step="0.001" inputmode="decimal" placeholder="Ej. 2,5"></details>
<?php if($sector==='mostrador'):?><div id="reason-area" class="reason-area hidden"><label for="reason">Motivo del descarte</label><select id="reason"><option value="sin_vender">Producto sin vender</option><option value="vencido">Vencido</option><option value="error_elaboracion">Error de elaboración</option><option value="quemado">Quemado</option><option value="danado">Dañado</option><option value="otro">Otro</option></select></div><?php endif; ?>
<div class="dialog-actions"><button class="btn-outline" value="cancel" type="submit">Cancelar</button><button id="submit-action" type="button" class="btn-primary" disabled>Confirmar movimiento</button></div>
</form></dialog>
<div role="status" aria-live="polite" id="toast" class="toast hidden"></div>
<script src="../assets/terminal.js" defer></script></body></html>
