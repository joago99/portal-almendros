# Portal Clientes — Los Almendros v1.0.0

Sistema de gestión de proyectos, clientes, pagos y documentos para **Constructora Los Almendros**.

**URL:** https://portal.constructoralosalmendros.cl  
**Sitio principal:** https://constructoralosalmendros.cl

---

## Funcionalidades

### Resumen
Dashboard con estadísticas generales: proyectos activos, pagos pendientes/atrasados/cobrados.

### Proyectos
- CRUD completo (crear, editar, eliminar)
- Filtro por estado (activo, pausado, finalizado)
- Detalle expandible con pagos y documentos asociados
- Historial de cambios de presupuesto
- Cada proyecto muestra: cliente, presupuesto, pagado, pendiente, atrasado, documentos

### Clientes
- CRUD completo
- Totales: presupuesto aprobado y pagado a la fecha
- "Ver obras" muestra proyectos del cliente en popup
- Gestión de acceso (crear usuario cliente)

### Pagos
- CRUD completo con modal popup
- Filtro por estado: pendientes, atrasados, pagados
- Edición de estado y fecha de pago
- Eliminación

### Documentos
- Subida individual y múltiple
- Filtro por proyecto y cliente
- Selección múltiple + eliminación en lote

### Admin (solo usuarios admin)
- Dashboard con stats: total usuarios, activos, expirados, por rol
- CRUD de usuarios (crear, editar, eliminar)
- Asignación de rol (admin/staff/client) y cliente
- Fecha de caducidad de usuarios (login automático bloqueado)
- Activar/desactivar usuarios

---

## Accesos

| Usuario | Email | Rol | Contraseña |
|---|---|---|---|
| Joago | joago@losalmendros.cl | Admin | admin123 |
| Admin | admin@losalmendros.cl | Admin | admin123 |
| Falcon | falcon@losalmendros.cl | Staff | partner123 |
| Tiguer_buin | tiguer@losalmendros.cl | Staff | partner123 |
| Cristóbal | cristobal@losalmendros.cl | Staff | construye2026 |
| Cliente Prueba | cliente@losalmendros.cl | Cliente | cliente123 |

---

## Stack técnico

| Componente | Tecnología |
|---|---|
| Frontend | HTML + CSS + JavaScript (vanilla) |
| Backend | PHP 8.3 (sin frameworks) |
| Base de datos | SQLite |
| Servidor web | Apache / LiteSpeed (Hostinger) |
| DNS + SSL | Cloudflare |
| Diseño | Inter + sistema de colores teal |

---

## Despliegue

Ver [DEPLOY.md](./DEPLOY.md) para instrucciones completas de deploy.

### Producción actual
- **Hosting:** Hostinger (compartido PHP)
- **Servidor:** LiteSpeed + PHP 8.3 + SQLite
- **URL:** https://portal.constructoralosalmendros.cl
- **DNS:** Cloudflare (proxy activo, SSL Flexible)

---

## Changelog

### v1.0.0 (2026-06-30)
- Lanzamiento inicial del portal
- CRUD proyectos, clientes, pagos, documentos
- Panel admin con gestión de usuarios y caducidad
- Integración con constructoralosalmendros.cl
- Deploy en Hostinger + Cloudflare
