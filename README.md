# Certificados

Plugin de WordPress para gestionar y emitir certificados.

## Estado

Primera versión funcional en desarrollo. Incluye:

- Registro de cursos o talleres.
- Registro de certificados asignados a clientes.
- Código único de validación por certificado.
- Descarga de PDF desde “Mi cuenta” de WooCommerce.
- Ruta pública de validación con QR.
- Capacidades para administradores y gestores de tienda.

## Instalación local

1. Copia esta carpeta dentro de `wp-content/plugins/certificados`.
2. Activa el plugin desde el panel de administración de WordPress.

## Desarrollo

Archivo principal del plugin:

```text
certificados.php
```

## Flujo básico

1. En el administrador de WordPress, entra a **Cursos y talleres** y crea un curso.
2. En **Cursos y talleres > Certificados**, crea un certificado.
3. Selecciona el curso, el cliente y la fecha de emisión.
4. Publica el certificado.
5. El cliente verá el certificado en **Mi cuenta > Certificados**.
6. Desde esa vista podrá descargar el PDF o abrir la validación pública.

El PDF incluye los datos del participante, el curso, la fecha, el código de validación y la URL pública. Cuando el sitio puede consultar QuickChart, también incrusta el QR dentro del PDF.

## Ruta de validación

Cada certificado publicado genera un código único. La URL pública usa este formato:

```text
/validar-certificado/CODIGO
```
