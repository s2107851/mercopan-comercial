# Instalación y despliegue — Mercopan Comercial

## Instalación automática en Windows con XAMPP

1. Poné la carpeta del proyecto directamente dentro de `htdocs` y arrancá Apache y MySQL.
2. En la PC principal, hacé doble clic en **Instalar-Mercopan.cmd**. Ingresá la clave de root de MySQL (Enter si está vacía) y elegí la contraseña de Administración.
3. Abrí la dirección de Administración que aparece al finalizar y creá el primer empleado. Para otra computadora del mismo Wi-Fi, usá la dirección de Depósito o Mostrador que muestra el instalador. Las direcciones suponen Apache en el puerto 80.

El instalador usa el puerto local de MySQL de XAMPP, crea las tablas y genera credenciales propias para Mercopan. La base empieza vacía. Si ya hay una instalación configurada, comprueba la conexión. Si encuentra una base previa sin su configuración, se detiene para conservar los datos. Los requisitos de OCR se detallan en la guía de instalación.


## 1. Requisitos

- Windows con XAMPP o servidor Linux con Apache/Nginx.
- PHP **8.1 o superior** con `pdo_mysql`, `fileinfo`, sesiones activas y permiso para escribir en `storage/facturas`.
- MySQL 8+ o MariaDB 10.5+ (tablas InnoDB, transacciones y restricciones `CHECK`).
- Para OCR de imágenes: Tesseract 5+ con idioma español (`spa`), opcional inglés (`eng`).
- Para PDF escaneados: Poppler `pdftoppm`. Para PDF digitales: Poppler `pdftotext` (configurado en PATH).
- Para OCR, PHP debe tener permitido `proc_open`; el proceso del servidor necesita poder ejecutar los programas anteriores.
- Navegadores actualizados con soporte JavaScript, `fetch()` y `<dialog>`.

**IMPORTANTE:** Un sistema así debe probarse con usuarios reales antes de entrar en funcionamiento diario. Ver `PRUEBAS_ACEPTACION.md`.

## 2. Preparar la base, sin tocar UTU

En **una instancia o base de datos independiente**, importá **solamente** `database/001_schema.sql` en phpMyAdmin/MySQL. Crea la base `mercopan_comercial` y sus tablas. **No importes este esquema sobre `mercopan_utu` ni sobre datos reales existentes.**

Para probar con nombres ficticios, y **solo en una base nueva/vacía**, importá adicionalmente `database/002_demo_SOLO_PRUEBAS.sql`. Es opcional. Esta prueba deja el stock en cero y obliga a cargar una factura antes de retirar mercadería.

Crear usuario MySQL de mínimo privilegio (ejecutar como administrador MySQL):

```sql
CREATE USER 'mercopan_app'@'localhost' IDENTIFIED BY 'UNA_CLAVE_LARGA_NUEVA';
GRANT SELECT, INSERT, UPDATE, DELETE ON mercopan_comercial.* TO 'mercopan_app'@'localhost';
```

Según el host real, se puede requerir `'mercopan_app'@'127.0.0.1'` en vez de `'localhost'`; ajustarlo al DSN y al servidor. No conceder permisos globales.

## 3. Configuración privada

Copiá `app/config.local.example.php` a **`app/config.local.php`**. Indicá host, puerto, usuario, contraseña y nombre de la nueva base. No publiques ese archivo ni lo adjuntes a GitHub.

En Windows/XAMPP, desde CMD o PowerShell, generar hash para la contraseña administrativa con PHP instalado en XAMPP:

```powershell
C:\xampp\php\php.exe -r "echo password_hash('CAMBIAR_POR_CLAVE_LARGA', PASSWORD_DEFAULT), PHP_EOL;"
```

Pegá el valor completo resultante en `admin_password_hash` del archivo privado. La contraseña de ejemplo no debe usarse en una instalación real. Es recomendable administrar la máquina por red local segmentada, con contraseña única y respaldos.

La opción `session_secure` se debe poner en `true` cuando se sirva bajo **HTTPS**.

## 4. Ubicación, URLs y tres terminales

Para pruebas rápidas en XAMPP, copiá la carpeta `Mercopan_Comercial` a `C:\xampp\htdocs\Mercopan_Comercial` e iniciá Apache/MySQL. En el navegador local:

- Depósito: `http://localhost/Mercopan_Comercial/public/deposito/`
- Mostrador: `http://localhost/Mercopan_Comercial/public/mostrador/`
- Administración: `http://localhost/Mercopan_Comercial/public/admin/`

Para otras pantallas de la misma red, cambiar `localhost` por la IP **fija del servidor**, sin inventar direcciones (ejemplo conceptual: `http://IP_DEL_SERVIDOR/Mercopan_Comercial/public/deposito/`). Cada terminal abre únicamente su ruta en modo pantalla completa. No hay navegación entre sectores desde las interfaces táctiles.

**Para producción:** crear un virtual host cuyo `DocumentRoot` apunte a `.../Mercopan_Comercial/public` y publicar las rutas `/deposito/`, `/mostrador/` y `/admin/`. Mantener `app`, `storage`, `docs` y `database` fuera del directorio servido. Los `.htaccess` incluidos son una medida adicional para XAMPP, no sustituyen un DocumentRoot seguro.

## 5. OCR

1. Instalar Tesseract con datos de idioma español; probar en terminal `tesseract --list-langs`.
2. Instalar Poppler con `pdftoppm` y `pdftotext` disponibles en PATH.
3. En Windows, configurar `ocr_tesseract` y `pdf_pdftoppm` con las rutas completas de los ejecutables, si no están en PATH. `pdftotext` por ahora se resuelve por PATH.
4. En Administración → Facturas, subir una foto JPG/PNG o PDF; comprobar lectura y abrir el borrador editable.
5. **Comprobar proveedor, documento, fecha, cantidades, unidades, importes y correspondencia con ingredientes reales**. Guardar borrador y revisar; recién entonces pulsar *Confirmar e ingresar stock*.

Si no hay OCR instalado, el archivo queda almacenado pero la carga debe hacerse manualmente; se informa la falta de OCR. En PDFs se limitan las imágenes a las primeras tres páginas (en PDFs digitales se usa texto completo si existe). No realizar pruebas de producción con facturas de proveedores sin consentimiento.

## 6. Primer uso recomendado

1. Entrar a Administración con la contraseña configurada.
2. Crear por lo menos un empleado (si la base es vacía) y seleccionarlo en la parte superior.
3. Crear proveedores, materias primas, productos elaborados y recetas.
4. Cargar y **confirmar** una factura real o de prueba para disponer de stock en depósito.
5. Abrir la terminal de depósito y seleccionar el empleado del turno. Retirar la cantidad que pasa a producción.
6. Desde Administración → Producción, registrar lotes; sus ingredientes se descuentan del área de producción.
7. Desde Mostrador, registrar ventas o descartes. Consultar Stock, Trazabilidad y Reportes en Administración.

## 7. Seguridad antes de operar

- Permitir acceso únicamente desde la red local autorizada o VPN; bloquear exposición pública del puerto web y del servidor MySQL.
- Usar HTTPS, incluso en LAN cuando sea posible, especialmente en administración, y poner `session_secure=true`.
- Mantener phpMyAdmin no expuesto; las credenciales MySQL son diferentes de la clave administrativa.
- Las terminales **no autentican la identidad personal**: seleccionar un nombre solo registra una declaración de responsable. Para procesos críticos, supervisar físicamente o agregar PIN/badge específico en una futura versión.
- Mantener el equipo servidor protegido y el software actualizado. Probar recuperación de datos y registrar accesos.
- El archivo local `app/config.local.php` y los documentos de `storage/facturas` nunca deben quedar accesibles desde la web.
- Nunca usar el script de DEMO en una base con datos reales. No modificar `mercopan_utu`.

## 8. Respaldos

Crear respaldo SQL y de documentos (son **dos** fuentes de datos) en una carpeta fuera del servidor, de preferencia con cifrado:

```bash
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 -u mercopan_app -p mercopan_comercial > mercopan_respaldo.sql
```

Si este usuario no posee privilegios específicos de `mysqldump`, crear un usuario de respaldo de solo lectura apropiado y sin permisos de escritura. Respaldar además toda la carpeta `storage/facturas` y la configuración privada en un almacén seguro. Programar respaldo diario, verificar su tamaño y probar restauraciones periódicas en una **base de ensayo**. No copiar la base a cuentas personales o repositorios.

## 9. Pruebas

Sin MySQL, ejecutar `php tests/unit.php` y validaciones sintácticas `php -l archivo.php`. Con MySQL, seguir `docs/PRUEBAS_ACEPTACION.md` y completar todas las casillas, incluyendo dos terminales simultáneas, OCR y restauración de respaldo.

## 10. Estado de entrega

Esta edición incluye módulos reales y SQL, **no está certificada para producción**. No se han efectuado pruebas con MySQL real, el hardware, las facturas reales ni los empleados de la empresa en este entorno. No sustituye facturación electrónica ni garantiza integración con cajas registradoras existentes. La integración requiere una etapa de aceptación y seguridad en el local.
