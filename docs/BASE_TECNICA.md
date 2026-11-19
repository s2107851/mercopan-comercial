# Decisiones técnicas y diferencias con el proyecto académico

## Referencia de origen

Se revisó el repositorio académico `https://github.com/s2107851/Mercopan-utu` (rama `main`), identificando como referencia `includes/funciones.php`, `includes/compras.php`, `includes/facturas.php`, `includes/produccion.php`, `includes/ventas.php`, `includes/salidas.php`, `includes/reportes.php`, `responsable.php`, `database/*.sql`.

Funcionalidades que existían conceptualmente: catálogo, selección de responsable, compras de ingredientes, facturas manuales con borrador y confirmación, recetas y producción, ventas, pérdidas y donaciones, auditoría y transacciones de MySQL. En la versión académica el stock de ingredientes se descontaba directamente en la elaboración y no existían pantallas táctiles separadas ni OCR real.

## Qué se hizo aquí

Se creó una **base comercial independiente** y se adaptaron los patrones útiles de transacciones, validación y separación de responsabilidades. El código actual se escribió para este diseño y no se ha reemplazado el repositorio de UTU ni importado sus datos ficticios al negocio real.

### Circuito de existencias

```text
Factura confirmada
       ↓ (ingresa al stock_deposito)
 Depósito --retiro--> Área de producción
                           ↓ (receta y lote)
                   Productos terminados
                         ↙         ↘
                      Venta       Descarte
```

**Invariante clave:** el retiro hace `stock_deposito -= Q` y `stock_produccion += Q`. La receta consume `stock_produccion -= Q`, **sin volver a tocar `stock_deposito`**. Vender o descartar reduce solamente `productos.stock`. Cada paso y su autor quedan en `movimientos` y `auditoria`.

Una factura guardada como borrador **no modifica existencias**. Confirmarla agrega todos sus ingredientes en una única transacción. Si falla una fila, no se ingresa ninguna. La combinación proveedor+número se controla al confirmar, y para archivos repetidos se compara SHA-256.

### Datos y cálculos

- Cantidades: columnas `DECIMAL(13,3)` con validación de unidad entera cuando aplica.
- Importes: `DECIMAL(13,2)`; costos unitarios estimados `DECIMAL(...,4)`.
- El costo promedio de ingrediente se calcula con unidades existentes en **ambas** ubicaciones para no contar dos veces.
- Si un ingrediente existente no tiene costo conocido, no se inventa: su promedio es `NULL`. La producción sin costo conocido conserva costo indeterminado.
- La rentabilidad bruta estimada no incluye mano de obra, energía, transporte, sueldos, impuestos ni gastos indirectos; compras de un mes **no** equivalen a costo de ventas del mes.
- Los registros revertidos siguen en tablas, con fecha de reversión y movimientos compensatorios. Las estadísticas de ventas y descartes los excluyen.
- Los gráficos incluyen únicamente hechos registrados y no suponen ventas o producción en días sin registro.

### Seguridad y trazabilidad

- PDO con consultas preparadas y transacciones InnoDB; claves de operación únicas en `operaciones`.
- CSRF y cookies HttpOnly + SameSite; sesión administrativa por contraseña hasheada.
- Selección de empleado declarativa en terminales: no es autenticación fuerte.
- OCR por ejecutables locales con lista cerrada de argumentos y carga de archivos fuera de `public`.
- Control optimista de versión de borrador en facturas y bloqueos de fila `FOR UPDATE` en stock.
- Uso exclusivamente recomendado dentro de LAN restringida/VPN, previa revisión de seguridad.

## Pendientes de aceptación / evolución

- Integración real de escáneres, calibres, balanzas, cajas existentes o facturación electrónica si el dueño lo solicita expresamente.
- Verificación de OCR sobre facturas auténticas de cada proveedor y mejora de mapeo de renglones.
- Pruebas con MySQL, consultas concurrentes, fallos de red, recuperación de respaldos y experiencia en tablet/kiosco.
- Mejoras de costo por lotes, merma de producción y medidas finas de rentabilidad; no afirmar valores desconocidos.
- Control de permisos por rol administrativo más granular, PIN de supervisor o lector de credenciales según riesgos reales del local.
- Exportación avanzada y modelos predictivos de producción, solo cuando haya datos suficientes y validados.
