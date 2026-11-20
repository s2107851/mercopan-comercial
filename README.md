# MERCOPAN — Sistema comercial de gestión interna

## Instalación automática en Windows con XAMPP

1. Poné la carpeta del proyecto directamente dentro de `htdocs` y arrancá Apache y MySQL.
2. En la PC principal, hacé doble clic en **Instalar-Mercopan.cmd**. Ingresá la clave de root de MySQL (Enter si está vacía) y elegí la contraseña de Administración.
3. Abrí la dirección de Administración que aparece al finalizar y creá el primer empleado. Para otra computadora del mismo Wi-Fi, usá la dirección de Depósito o Mostrador que muestra el instalador. Las direcciones suponen Apache en el puerto 80.

El instalador usa el puerto local de MySQL de XAMPP, crea las tablas y genera credenciales propias para Mercopan. La base empieza vacía. Si ya hay una instalación configurada, comprueba la conexión. Si encuentra una base previa sin su configuración, se detiene para conservar los datos. Los requisitos de OCR se detallan en la guía de instalación.


**Edición de desarrollo 0.1 · 18/11/2026 · Salto, Uruguay**

Aplicación web en español para **depósito**, **mostrador (ventas y descartes)** y **administración**, con un único backend PHP/MySQL y tres entradas independientes. El proyecto comercial **no comparte base de datos** con el proyecto académico de UTU.

## Carpetas

```text
app/            Configuración, validaciones, inventario, OCR, facturas, informes y vistas PHP
public/
  deposito/      Terminal táctil para retiro del depósito
  mostrador/     Terminal táctil de ventas y descartes
  admin/         Acceso administrativo y control de gestión
  assets/        CSS y JavaScript compartidos
storage/facturas/ Documentos originales protegidos (fuera de public)
database/       Esquema vacío y datos DEMO explícitamente separados
docs/           Instalación, seguridad y protocolo de pruebas
tests/          Pruebas unitarias sin DB
```

## Qué hace en esta versión

- Registro de empleados por selección al iniciar un turno; contraseña separada **solo** para administración.
- Alta y edición de proveedores, ingredientes, productos, empleados y recetas.
- Pantalla táctil de retiro: elegir ingrediente > cantidad frecuente o libre > confirmar; mueve depósito → área de producción.
- Pantalla táctil de mostrador: venta, motivo de descarte, precio histórico y costo estimado cuando se conoce.
- Corrección de último movimiento (5 minutos y mismo empleado) sin eliminar el original; registra movimiento de reversión.
- Producción por lotes con receta, consumo **del área de producción** y aumento del stock terminado. No descuenta dos veces la materia prima.
- Facturas proveedor: PDF/foto, OCR local real (si Tesseract y Poppler están instalados), borrador editable, validación humana, control de duplicados por proveedor+número/hash de archivo, confirmación transaccional que incorpora materia prima al depósito.
- Historial inmutable de movimientos y auditoría; ajustes físicos con motivo.
- Estadísticas con fechas, costos y gráficos de datos registrados; muestra información insuficiente y costos desconocidos como tales.
- SQL de instalación y datos de demostración por separado.

## Inicio rápido

Leé **[docs/INSTALACION.md](docs/INSTALACION.md)** antes de ejecutar cualquier archivo. En XAMPP, copiá la carpeta a `htdocs`, importá `database/001_schema.sql` en MySQL y configurá `app/config.local.php` usando el ejemplo. Luego entrá en `http://localhost/Mercopan_Comercial/public/admin/`.

No uses datos reales durante las pruebas iniciales. Los archivos adjuntos se almacenan fuera de `public/`.

El alcance exacto y los pendientes están detallados en [docs/ESTADO_FUNCIONAL.md](docs/ESTADO_FUNCIONAL.md).

## Estado de calidad

- Código PHP: verificado sintácticamente con PHP 8.4.
- Validaciones de cantidades, fechas y sugerencias de OCR: pruebas unitarias locales exitosas (`php tests/unit.php`). También se comprobó la lectura Tesseract sobre una imagen sintética de factura (no es una factura real de proveedor).
- **No se han probado transacciones contra MySQL real ni equipos táctiles físicos en este entorno.** Antes de utilizarlo operativamente, ejecutar pruebas de integración, seguridad, respaldos y aceptación con personal de la panadería.
- No reemplaza facturación electrónica, contabilidad legal ni punto de venta fiscal existente.

## Antecedentes y propiedad del proyecto

El diseño parte de la especificación `message.txt` del proyecto comercial y estudia la implementación académica anterior `s2107851/Mercopan-utu` como referencia de flujos. **Esta entrega es un proyecto comercial nuevo**, no una modificación del repositorio académico. Ninguna base original se sobrescribe. Más información en [docs/BASE_TECNICA.md](docs/BASE_TECNICA.md).
