# Trabajo entre dos — Mercopan Comercial

## Qué repositorio es

Este repositorio es **solo** el proyecto comercial real de Mercopan, Salto, Uruguay.
`Mercopan-utu` y `proyecto-gestion` son proyectos académicos finalizados y no deben mezclarse aquí.

## Invitación

En GitHub: `Settings` > `Collaborators` / `Collaborators and teams` > `Add people`.
Invitar por el usuario GitHub del compañero; cuando acepte podrá clonar el repositorio privado.

## Flujo simple y seguro

1. Antes de trabajar, actualizar la rama principal: `git pull`.
2. Para un cambio, usar rama propia: `git switch -c mejora/instalador`.
3. Hacer commits chicos con mensajes descriptivos: `git add .` y `git commit -m "..."`.
4. Subirla: `git push -u origin mejora/instalador`.
5. Abrir un Pull Request y revisar antes de fusionar con `main`.

No subir `app/config.local.php`, `.env`, facturas de proveedores ni respaldos de bases de datos reales. El `.gitignore` cubre las rutas habituales; revisar siempre `git status` antes de subir.

## Próximos pasos acordados

- Instalador local de primera ejecución sin pedir host, puerto ni credenciales al usuario final.
- Configuración de MySQL solo en la PC principal. Nunca abrir 3306 a toda la red.
- Pruebas entre dos computadoras del mismo Wi-Fi con una única base de datos.
- Facilitar acceso a `/admin/`, `/deposito/` y `/mostrador/`.
- Validar autenticación, permisos, OCR y resguardos antes de la instalación comercial.
