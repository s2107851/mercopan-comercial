# Estado funcional de la versión 0.1

Esta tabla distingue **código presente** de **funcionalidad aceptada en el local**. El hecho de aparecer implementada no significa que ya se haya probado contra el MySQL real de Mercopan.

| Área | Estado de desarrollo | Pendiente de aceptación |
|---|---|---|
| Arquitectura PHP/MySQL compartida, 3 interfaces separadas | Implementado en código | Instalar en servidor y comprobar redes |
| Terminal táctil retiro depósito | Implementada: producto, atajo, confirmar, devolución rápida | Probar tamaño de pantalla, guantes/manos con harina |
| Terminal táctil ventas y descartes | Implementada: registrar, descontar, motivo, revertir | Validar catálogo y unidades reales; comparar con ventas de caja |
| Identificación rápida de responsables | Implementada, declarativa | Definir controles de identidad operativos |
| Acceso administrativo con contraseña | Implementado con hash PHP | HTTPS, firewall, permisos, política de contraseñas |
| Gestión catálogos y recetas | Implementado CRUD básico + estado activo/inactivo | Probar catálogo verdadero de Mercopan |
| Gestión de producción | Implementado por lotes y receta | Validar rendimiento y cantidades reales |
| Facturas con borrador, revisión y confirmación | Implementado transaccional | Prueba en MySQL y tratamiento de excepciones comerciales |
| OCR de JPG/PNG/PDF | Integración local con Tesseract y Poppler | Instalar ejecutables; afinar reconocimiento de proveedores/líneas reales |
| Duplicados de facturas | Proveedor+número y hash de documento confirmado | Confirmar reglas para notas de crédito/series |
| Historial y correcciones | Implementado por movimientos y auditoría, corrección rápida y ajuste físico | Definir procedimiento de rectificación de facturas confirmadas |
| Reportes y gráficos | Implementados con SQL y gráficos HTML/CSS; filtros y CSV | Verificar cifras con documentos internos |
| Costo y margen bruto estimado | Implementados con costos desconocidos indicados | Completar costos de ingredientes, mano de obra y otros gastos |
| Recomendaciones sobre historial | Observaciones descriptivas con umbrales mínimos | Modelos de pronóstico cuantitativo requieren histórico verificado |
| Pruebas unitarias sin DB | Ejecutadas en este entorno | No reemplazan pruebas reales de integración |
| Pruebas MySQL, rendimiento, pantallas y restauración | **No realizadas aquí** | Obligatorio antes de operación cotidiana |

## Limitaciones conscientes

- No se emiten comprobantes fiscales ni se integra facturación electrónica; el módulo de ventas es control interno.
- El OCR sugiere datos y **nunca confirma solo**. Una factura irregular o muy compleja puede necesitar carga manual.
- El costo se calcula a partir de ingredientes; todavía no incluye gastos de operación generales ni inventario por lotes/lotes de vencimiento.
- Si se necesita reversión de facturas confirmadas, debe desarrollarse un procedimiento contable y de stock que respete consumos ya efectuados. Hoy solo pueden cancelarse borradores; ajustar stock no equivale a una nota de crédito.
- Las estadísticas no inventan datos; no se genera automáticamente un plan de producción sin histórico suficiente.
- El historial de movimiento conserva eventos, pero la selección de empleado sin credencial fuerte **no garantiza identidad**.
