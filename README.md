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
- Asignación masiva de certificados a varios clientes.
- Búsqueda de clientes por AJAX para tiendas con muchos usuarios.
- Shortcode compatible con Elementor para páginas de validación.
- PDF en formato horizontal con marco, color de marca `#feb20b`, código QR y logotipo del sitio (con conversión dinámica de PNG indexados y fallback por defecto).
- Plantilla de PDF tipo diploma inspirada en el certificado de The Homebrewer Peru, con mensaje editable por certificado o por asignación masiva.
- Solicitud de certificados desde “Mi cuenta” con bandeja de aprobación manual en WordPress.

## Instalación local

1. Copia esta carpeta dentro de `wp-content/plugins/certificados`.
2. Activa el plugin desde el panel de administración de WordPress.

También puedes generar un ZIP instalable desde WordPress:

```bash
bash tools/build-plugin-zip.sh
```

El archivo queda en:

```text
dist/certificados.zip
```

## Desarrollo

Archivo principal del plugin:

```text
certificados.php
```

Verificación estática del flujo esencial:

```bash
php tools/verify-objective.php
```

Prueba ligera de carga del plugin con stubs de WordPress:

```bash
php tools/test-plugin-bootstrap.php
```

Prueba ligera del flujo de “Mi cuenta” con certificado simulado:

```bash
php tools/test-frontend-flow.php
```

## Flujo básico

1. En el administrador de WordPress, entra a **Cursos y talleres** y crea un curso.
2. En **Cursos y talleres > Certificados**, crea un certificado.
3. Selecciona el curso, el cliente, la fecha de emisión y ajusta el mensaje si hace falta.
4. Publica el certificado.
5. El cliente verá el certificado en **Mi cuenta > Certificados**.
6. Desde esa vista podrá descargar el PDF o abrir la validación pública.

El PDF se genera en formato horizontal (Landscape) con una plantilla tipo diploma: fondo oscuro, área central blanca, marco dorado `#feb20b`, banda honorífica, nombre del participante destacado, mensaje editable, fecha con formato “Lima 23 de MAYO del 2026”, logotipo de *The Homebrewer Peru* y código QR incrustado para validación rápida con QuickChart. El logotipo usa el logo configurado del tema o, si no hay uno, el fallback `https://thehomebrewerperu.com/wp-content/uploads/2019/12/Logo_thbp.png`.

Las fechas del curso son referenciales y opcionales. Para cursos que se repiten cada mes, usa la fecha de emisión del certificado.

## Solicitudes de clientes

En **Mi cuenta > Certificados**, el cliente puede usar el botón **Solicitar certificado** para enviar su nombre completo, correo y el curso que tomó. El formulario usa la misma línea visual del PDF: negro, dorado `#feb20b`, bordes sobrios y botones de marca.

Las solicitudes llegan a **Cursos y talleres > Solicitudes de certificado**. Desde cada solicitud, un administrador o gestor de tienda puede seleccionar el curso real, revisar fecha/mensaje y aprobarla. Al aprobar, el plugin crea el certificado publicado y queda visible automáticamente para el cliente en **Mi cuenta > Certificados**.

## Asignación masiva

Entra a **Cursos y talleres > Asignar certificados** para seleccionar un curso, definir fecha y mensaje, buscar clientes por nombre/correo y crear certificados para varios clientes a la vez.

## Ruta de validación

Cada certificado publicado genera un código único. La URL pública usa este formato:

```text
/validar-certificado/CODIGO
```

Para que la validación se integre perfectamente con constructores visuales como Elementor y cargue correctamente las cabeceras, menús, pie de página e iconos del tema (evitando conflictos o que paneles laterales se muestren desplegados), es altamente recomendable crear una página física en WordPress con el slug `validar-certificado` y colocar dentro de ella el shortcode:

```text
[certificados_validacion]
```

También puedes pasar un código específico en el shortcode:

```text
[certificados_validacion codigo="CERT-XXXX"]
```
