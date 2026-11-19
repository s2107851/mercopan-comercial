# Próximo cambio técnico: instalación sencilla

**Estado: pendiente.** Este kit crea el repositorio y documenta la prueba en red. No reemplaza ni modifica el código PHP actual.

## Objetivo

En la PC donde se instala Mercopan, el usuario de negocio NO ingresa host, puerto ni credenciales de MySQL.

## Implementación recomendada

1. Asistente de primer uso accesible **solo desde localhost** antes de configurar el sistema, con una clave de instalación de un solo uso o ejecución por consola. No exponerlo por LAN.
2. Detectar MySQL local en `127.0.0.1` y puertos habituales; si no existe, mostrar una instrucción clara para iniciar/instalarlo, no inventar una conexión.
3. Solicitar únicamente autorización administrativa durante la instalación, cuando MySQL la requiera. No mostrar estas credenciales a empleados ni almacenarlas en Git.
4. Crear `mercopan_comercial` en una instalación nueva y vacía; validar si ya existe para impedir borrar datos. Aplicar migraciones SQL idempotentes controladas, nunca importar `002_demo_SOLO_PRUEBAS.sql` por defecto.
5. Crear un usuario SQL de mínimos privilegios y contraseña aleatoria, guardar configuración PHP local fuera de la carpeta web, con permisos restringidos.
6. Pedir y validar una contraseña robusta de administración de Mercopan. Proteger el asistente contra ejecuciones repetidas; no permitir instalación con visitantes de LAN.
7. Generar accesos directos a las tres rutas en la PC principal y mostrar la IP local para los otros equipos.
8. Mantener la aplicación y las facturas solo en la PC principal. Las otras computadoras acceden por navegador; no necesitan instalar MySQL.
9. Registrar errores, permitir reintento seguro sin borrar datos existentes y documentar un procedimiento de restauración/backup.

Antes de implementar, revisar `app/config.local.example.php`, bootstrap de conexión, `database/001_schema.sql` y rutas actuales, para integrar sin romper la aplicación.
