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
3. Selecciona el curso, el cliente y la fecha de emisión.
4. Publica el certificado.
5. El cliente verá el certificado en **Mi cuenta > Certificados**.
6. Desde esa vista podrá descargar el PDF o abrir la validación pública.

El PDF se genera en formato horizontal (Landscape) e incluye los datos del participante, el curso, la fecha, el código de validación, la URL pública, un marco elegante con color de marca `#feb20b`, el logotipo del sitio (con conversión automática de imágenes no compatibles como PNGs indexados, GIFs o WebP gracias a PHP GD, y fallback al logotipo de *The Homebrewer Peru* si no hay uno configurado) y el código QR incrustado dentro del PDF para su validación rápida con QuickChart.

Las fechas del curso son referenciales y opcionales. Para cursos que se repiten cada mes, usa la fecha de emisión del certificado.

## Asignación masiva

Entra a **Cursos y talleres > Asignar certificados** para seleccionar un curso, buscar clientes por nombre/correo y crear certificados para varios clientes a la vez.

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
