'use strict';
(()=>{
  document.querySelectorAll('[data-add-rows]').forEach(btn=>btn.addEventListener('click',()=>{
    const name=btn.dataset.addRows;
    const tbody=document.getElementById(name+'-rows');
    const template=document.getElementById(name+'-template');
    if(!tbody||!template||tbody.rows.length>=50){alert('Máximo 50 renglones visibles.');return;}
    tbody.append(template.content.cloneNode(true));
  }));
  document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{
    const action=form.dataset.confirm;
    const message=action==='confirmar'
      ?'¿Verificaste el documento, los importes y las cantidades? Al confirmar se actualizará inmediatamente el stock del depósito.'
      :'¿Cancelar este borrador sin ingresar mercadería?';
    if(!window.confirm(message))event.preventDefault();
  }));
})();
