# Checklist de aceptación en Mercopan (todavía no ejecutado en la empresa)

**Obligatorio antes de pasar a uso real.** Completar por el dueño/encargado y persona técnica sobre una base de ensayo.

## Configuración y seguridad

- [ ] PHP con PDO MySQL; MySQL InnoDB; carpetas protegidas; config privada; acceso administrativo con contraseña fuerte.
- [ ] Abrir depósito y mostrador en dos terminales reales de la red local, sin enlaces entre ellas.
- [ ] Ninguna ruta permite descargar `app/config.local.php` ni facturas sin estar autenticado en administración.
- [ ] Dos empleados diferentes seleccionados en terminales separadas quedan correctamente atribuidos.
- [ ] No hay acceso desde Internet ni desde una red invitada; se verificó firewall/VPN/HTTPS.

## Facturas y OCR

- [ ] Cargar JPG, PNG, PDF digital y PDF escaneado con una factura de ensayo realista.
- [ ] OCR lee o declara su incapacidad; **siempre** permite corregir proveedor, número, fecha, cantidades e importes.
- [ ] La factura en borrador no aumenta stock. Dos cambios del mismo borrador requieren revisión de versión.
- [ ] Confirmar 25 kg harina ($1250) y 30 huevos ($450) aumenta solo stock de depósito en esas cantidades.
- [ ] Intentar confirmar nuevamente el mismo documento no duplica stock.
- [ ] Intentar confirmar mismo proveedor+número con otro borrador es rechazado.
- [ ] Si un renglón es inválido, la transacción no registra un ingreso parcial.

## Inventario y producción

- [ ] Retirar 5 kg de harina: depósito -5, área de producción +5; movimiento y empleado visibles.
- [ ] Repetir la misma solicitud HTTP (misma `clave`): no se duplica el retiro.
- [ ] Corrigir el retiro dentro de 5 minutos: cantidades se revierten y se conserva historial.
- [ ] Si ya se consumió materia prima, no se permite corregir un retiro dejando stock negativo.
- [ ] Elaborar una receta que consume 5 kg harina del área de producción: depósito no se descuenta otra vez.
- [ ] Producir sin suficiente materia prima en área producción: no aparece producto terminado ni se registra consumo parcial.
- [ ] Receta actualizada no modifica consumos históricos de elaboraciones anteriores.
- [ ] Con stock terminado de 20 panes, registrar venta de 3 y descarte de 2: queda 15 panes; solo 3 generan ingreso.
- [ ] Venta y descarte simultáneos en dos pantallas jamás dejan stock negativo.
- [ ] Costo desconocido figura como «Sin datos» y no como beneficio ficticio.

## Reportes y respaldo

- [ ] Filtro por fechas coincide con los movimientos válidos y no incluye ventas/descartes revertidos.
- [ ] Las gráficas muestran compras, producción, ventas, descartes y proveedores realmente ingresados.
- [ ] Los motivos de descarte no se suman como venta.
- [ ] Historial muestra quién actuó, cuándo, cantidad y reversión.
- [ ] Se generó un respaldo SQL y de documentos y se probó restaurar ambos en otro servidor de prueba.
- [ ] Aceptación firmada por responsable de Mercopan, con correcciones registradas.

## Resultado

Instalación/equipo: __________   Fecha: __________  Responsable: __________

Observaciones: ___________________________________________

Autorización para operación:  **NO** hasta completar controles y subsanar errores.
