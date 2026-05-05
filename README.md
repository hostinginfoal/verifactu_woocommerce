# VeriFactu InFoAL para WooCommerce

<p align="center">
  <img src="assets/img/logo-infoal.png" alt="InFoAL Logo" width="180">
</p>

<p align="center">
  <strong>Plugin oficial de InFoAL para la integración de WooCommerce con Veri*Factu (AEAT)</strong><br>
  Automatiza el registro de facturas en la sede electrónica de la Agencia Tributaria española.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress" alt="WordPress 5.8+">
  <img src="https://img.shields.io/badge/WooCommerce-7.0%2B-96588a?logo=woocommerce" alt="WooCommerce 7.0+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/Licencia-Comercial-orange" alt="Licencia Comercial">
</p>

---

## ¿Qué hace este plugin?

A partir del 1 de julio de 2025, la normativa española obliga a determinados contribuyentes a utilizar sistemas de registro de facturación verificados por la AEAT. Este plugin conecta tu tienda WooCommerce con el servicio **Veri\*Factu de InFoAL** para:

- ✅ **Registrar automáticamente** cada factura en la AEAT en el momento en que se completa un pedido.
- 🔄 **Sincronizar el estado** de cada registro (Pendiente → Aceptado / Rechazado) mediante comprobaciones automáticas en segundo plano.
- 🖨️ **Inyectar el Código QR** verificable de la AEAT directamente en los PDFs de factura generados por plugins compatibles.
- 📋 **Panel de control completo** desde el área de administración de WordPress para consultar el histórico y los estados.
- ⚖️ **Aviso legal de inalterabilidad** integrado en la ficha del pedido, cumpliendo con la normativa española.

---

## Requisitos

| Requisito | Versión mínima |
|---|---|
| WordPress | 5.8 |
| WooCommerce | 7.0 (compatible con HPOS) |
| PHP | 7.4 (recomendado 8.x) |
| Extensiones PHP | `curl`, `json` |

**Requiere una licencia activa de [InFoAL](https://infoal.es).** Sin un token válido, la comunicación con la AEAT no es posible.

---

## Instalación

1. Descarga el plugin (`.zip`) desde tu área de cliente de InFoAL.
2. En tu WordPress, ve a **Plugins → Añadir nuevo → Subir plugin** y selecciona el `.zip`.
3. Activa el plugin.
4. Ve a **VeriFactu → Configuración** e introduce tu **Token de API** y el **NIF del emisor**.
5. Valida el token con el botón "Validar token" y, si todo es correcto, el plugin ya está operativo.

---

## Compatibilidad con motores de PDF

El plugin puede integrarse con módulos de generación de facturas PDF para obtener el número de factura secuencial legal y para inyectar el código QR de la AEAT en el documento. El motor se configura desde **VeriFactu → Configuración → Motor de facturación (PDF)**.

| Motor | Soporte | Notas |
|---|---|---|
| **PDF Invoices & Packing Slips** (WP Overnight) | ✅ Nativo | Lee `_wcpdf_invoice_number`. Inyecta QR automáticamente. |
| Número de Pedido WooCommerce | ✅ Fallback | Usa el número de pedido como referencia. Sin PDF externo. |
| Otros plugins | 🔜 Próximamente | Se añadirán en futuras versiones. |

---

## Características principales

### 🏦 Registro en la AEAT (Veri*Factu)
- Alta de facturas (F1) automática al completar un pedido.
- Alta de facturas de abono / rectificativas al crear un reembolso.
- Comprobación periódica del estado (CRON cada minuto).
- Botón de "Sincronizar ahora" en la ficha del pedido.
- Reintento automático de facturas con error de conectividad.

### 📊 Panel de Administración
- **Dashboard:** Resumen de facturas enviadas, pendientes y con error.
- **Facturas de Venta:** Listado de todas las facturas con su estado AEAT.
- **Facturas por Abono:** Listado de todas las rectificativas.
- **Registros de Facturación:** Log completo de todos los envíos a la AEAT.
- **Configuración:** Token, NIF, motor de PDF, opciones fiscales (OSS, Territorio especial, Recargo de Equivalencia).
- **Ayuda:** Diagnóstico del sistema y envío de informes al soporte de InFoAL.

### 🔒 Seguridad y Cumplimiento
- Bloqueo de pedidos aceptados por la AEAT (impide modificaciones ilegales).
- Aviso legal de inalterabilidad de factura en la ficha del pedido.
- Revisión de seguridad completa: nonces CSRF, prepared queries, output escaping.

---

## Estructura del proyecto

```
verifactu_infoal/
├── admin/                  # Clases y vistas del área de administración
│   ├── class-verifactu-admin.php
│   ├── class-verifactu-admin-ajax.php
│   ├── css/
│   ├── js/
│   ├── tables/             # WP_List_Table para cada listado
│   └── views/              # Plantillas PHP de cada página
├── api/                    # Clientes HTTP para comunicarse con la API de InFoAL
├── assets/                 # Imágenes y recursos estáticos
├── includes/               # Clases core del plugin
│   ├── class-verifactu-infoal.php      # Singleton principal
│   ├── class-verifactu-installer.php   # Activación, tablas y defaults
│   ├── class-verifactu-order-hooks.php # Hooks de WooCommerce
│   └── class-verifactu-autoloader.php
├── languages/              # Traducciones (.pot, .po)
├── services/               # Lógica de negocio
│   ├── class-verifactu-service-verifactu.php
│   ├── class-verifactu-service-facturae.php
│   └── class-verifactu-service-qr.php
├── composer.json
├── uninstall.php
└── verifactu_infoal.php    # Bootstrap del plugin
```

---

## Soporte

Para soporte técnico y licencias, contacta con el equipo de **InFoAL**:

- 🌐 Web: [https://infoal.es](https://infoal.es)
- 📧 Soporte: Panel de diagnóstico integrado en **VeriFactu → Ayuda**

---

## Licencia

Este plugin es software comercial de **InFoAL**. No está permitida su redistribución, modificación o uso sin una licencia activa. Todos los derechos reservados © InFoAL.
