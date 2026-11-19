# Prueba Mercopan Comercial entre dos computadoras (misma casa)

> Importante: esta prueba es para la aplicación YA INSTALADA. El nuevo instalador automático está **pendiente**. Hasta integrarlo, seguir la instalación actual del proyecto para base de datos y `app/config.local.php`.

## PC 1 (servidor)

1. Descomprimir Mercopan Comercial dentro de `C:\xampp\htdocs\Mercopan_Comercial`.
2. Iniciar **Apache** y **MySQL** en XAMPP. La base de datos MySQL se mantiene **solo** en esta PC.
3. Completar la configuración existente e importar `database/001_schema.sql` en una base vacía, siguiendo `INSTALACION.md`. **Nunca usar `mercopan_utu`**.
4. Comprobar en esta PC `http://localhost/Mercopan_Comercial/public/admin/`.
5. Ejecutar en PowerShell `powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\verificar-red.ps1` desde la carpeta Mercopan. El script muestra las posibles URLs LAN.
6. Verificar que la red Windows está clasificada como **Privada** y permitir únicamente el acceso entrante a Apache (puerto TCP 80) desde la red doméstica de confianza. No habilitar acceso desde Internet ni abrir MySQL (3306).

## PC 2 (cliente)

1. Conectarla al mismo Wi-Fi / router local (no a una red de invitados aislada).
2. Abrir en navegador la URL de Administración o Depósito que mostró la PC 1, por ejemplo `http://192.168.1.40/Mercopan_Comercial/public/deposito/` (la IP es solo **un ejemplo**, no copiarla literalmente).
3. Registrar una operación de prueba en una terminal, actualizar la otra y verificar que se refleja en la misma base de datos.
4. Revisar también `.../public/mostrador/` y `.../public/admin/`.

## Diagnóstico

- `localhost` funciona en PC 1, pero no en PC 2: usá la IP **de PC 1**, no localhost ni la IP del cliente.
- No abre desde PC 2: revisar IP, firewall, red privada, aislamiento de clientes del router y Apache escuchando en la interfaz LAN.
- No abre ni en PC 1: iniciar Apache, revisar conflictos del puerto 80, la carpeta y el log de XAMPP.
- Error de base: MySQL debe estar iniciado en PC 1 y la configuración de la aplicación debe usar la base comercial (no UTU).
- No compartir `config.local.php`, contraseñas o facturas en capturas públicas.

## Seguridad

La prueba usa HTTP dentro de una red doméstica de confianza. Antes de operar comercialmente, configurar HTTPS, credenciales administrativas fuertes, respaldos y restricciones de acceso. Nunca abrir puertos en el router para exponer la aplicación a Internet. Las terminales acceden por HTTP(S) a PHP en la PC 1: NO necesitan MySQL instalado ni conexión directa al puerto 3306.
