'use strict';
(()=>{
const root=document.body, sector=root.dataset.sector, csrf=root.dataset.csrf;
const $=id=>document.getElementById(id);
let items=[],selected=null,quantity='',mode=sector==='deposito'?'retirar':'vender',busy=false,employee=null;
const modal=$('quantity-dialog'),person=$('person-dialog');
function key(){
  if(window.crypto&&typeof crypto.randomUUID==='function')return crypto.randomUUID();
  // Para terminales HTTP en LAN: clave única de operación, sin valor de autenticación.
  const t=Date.now().toString(16).padStart(16,'0');
  const r=Array.from({length:4},()=>Math.floor(Math.random()*0xffffffff).toString(16).padStart(8,'0')).join('');
  return `${t}-${r}`;
}
function notify(msg,error=false){const t=$('toast');t.textContent=msg;t.className='toast '+(error?'error':'success');
  clearTimeout(notify.timer);notify.timer=setTimeout(()=>t.classList.add('hidden'),4300);
}
function btn(text,cls='',cb=null){let b=document.createElement('button');b.type='button';b.className=cls;b.textContent=text;if(cb)b.addEventListener('click',cb);return b;}
function qtyFormat(x){return new Intl.NumberFormat('es-UY',{maximumFractionDigits:3}).format(Number(x));}
function peso(x){return new Intl.NumberFormat('es-UY',{style:'currency',currency:'UYU'}).format(Number(x));}
async function refresh(){
  try{const r=await fetch('api.php',{cache:'no-store',credentials:'same-origin'}),j=await r.json();if(!j.ok)throw Error(j.error||'Error');items=j.items;employee=j.empleado;
    $('persona-label').textContent=employee?.nombre||'Elegir responsable';render();if(!employee&&!person.open)person.showModal();
  }catch(err){notify('Sin conexión al servidor. '+err.message,true);}
}
function render(){
 const filter=$('filter').value.trim().toLocaleLowerCase('es-UY');
 const list=items.filter(i=>i.nombre.toLocaleLowerCase('es-UY').includes(filter)||i.categoria.toLocaleLowerCase('es-UY').includes(filter));
 $('item-count').textContent=`${list.length} productos`;
 const catalog=$('catalog');catalog.replaceChildren();$('empty').classList.toggle('hidden',list.length!==0);
 for(const i of list){
   const card=btn('','touch-product',()=>choose(i));
   const ico=document.createElement('span');ico.className='touch-icon';ico.textContent=i.icono||'📦';
   const name=document.createElement('strong');name.textContent=i.nombre;
   const category=document.createElement('span');category.className='touch-meta';category.textContent=i.categoria;
   const stock=document.createElement('span');stock.className='touch-stock';stock.textContent=`Disponible: ${qtyFormat(i.stock)} ${i.unidad}`;
   card.append(ico,name,category,stock);
   if(sector==='mostrador'&&i.precio!==null){let price=document.createElement('span');price.className='touch-price';price.textContent=peso(i.precio);card.append(price);}
   catalog.append(card);
 }
}
function choose(i){
 if(!employee){person.showModal();return;}
 selected=i;quantity='';$('submit-action').disabled=true;$('dialog-title').textContent=i.nombre;
 $('chosen-icon').textContent=i.icono||'📦';$('stock-info').textContent=`Disponible: ${qtyFormat(i.stock)} ${i.unidad}`;
 $('quick-amounts').replaceChildren();$('custom-amount').value='';document.querySelector('.custom-details').open=false;
 const amounts=String(i.atajos||'1,2,5').split(',').map(s=>s.trim()).filter(Boolean);
 amounts.forEach(q=>{let b=btn(`${qtyFormat(q)} ${i.unidad}`,'quick',()=>{
   quantity=q;document.querySelectorAll('.quick').forEach(el=>el.classList.remove('selected'));b.classList.add('selected');
   $('custom-amount').value='';$('submit-action').disabled=false;
 });$('quick-amounts').append(b);});
 if(sector==='mostrador'){
   $('reason-area').classList.toggle('hidden',mode!=='descartar');
   $('reason').value='sin_vender';
 }
 modal.showModal();
}
async function request(data){
 const form=new URLSearchParams({csrf, ...data});
 const resp=await fetch('api.php',{method:'POST',credentials:'same-origin',body:form,headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},cache:'no-store'});
 const json=await resp.json();if(!json.ok)throw Error(json.error||'Error de operación');return json.data;
}
$('custom-amount').addEventListener('input',ev=>{
 quantity=ev.target.value;document.querySelectorAll('.quick').forEach(el=>el.classList.remove('selected'));
 $('submit-action').disabled=!Number.isFinite(Number(quantity))||Number(quantity)<=0;
});
$('submit-action').addEventListener('click',async()=>{
 if(busy||!selected||!quantity)return;
 busy=true;$('submit-action').disabled=true;
 try{
   const action=mode==='retirar'?'retirar':mode==='vender'?'vender':'descartar';
   const r=await request({action,id:String(selected.id),cantidad:String(quantity),motivo:sector==='mostrador' ? $('reason').value:'',clave:key()});
   modal.close();notify(r.mensaje||'Movimiento registrado');selected=null;await refresh();
 }catch(e){notify(e.message,true);$('submit-action').disabled=false;}finally{busy=false;}
});
$('change-person').addEventListener('click',()=>person.showModal());
document.querySelectorAll('[data-employee]').forEach(b=>b.addEventListener('click',async()=>{
 try{const res=await request({action:'empleado',id:b.dataset.employee});person.close();notify(res.mensaje);await refresh();}
 catch(e){notify(e.message,true);}
}));
$('undo-last').addEventListener('click',async()=>{
 if(!employee){person.showModal();return;}
 if(!confirm('¿Querés revertir el último movimiento de este tipo realizado por vos en los últimos 5 minutos? Quedará auditado.'))return;
 const type=mode==='retirar'?'retiro':mode==='vender'?'venta':'descarte';
 try{const r=await request({action:'revertir',tipo:type,clave:key()});notify(r.mensaje);await refresh();}
 catch(e){notify(e.message,true);}
});
$('filter').addEventListener('input',render);
document.querySelectorAll('[data-mode]').forEach(b=>b.addEventListener('click',()=>{
  mode=b.dataset.mode;document.querySelectorAll('[data-mode]').forEach(x=>x.classList.toggle('active',x===b));
  document.querySelector('h1').textContent=mode==='vender'?'¿Qué producto vendiste?':'¿Qué producto descartás?';
  $('undo-last').textContent=mode==='vender'?'↶ Corregir última venta':'↶ Corregir último descarte';
}));
refresh();setInterval(()=>{if(!modal.open&&!person.open)refresh();},15000);
})();
