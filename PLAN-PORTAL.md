# Portal de Proyectos - Construcciones Los Almendros

## Objetivo general
Portal privado: clientes ven avance, documentos y estado de pagos; staff/admin gestionan proyectos y cobros. Todo centralizado.

## Stack
- **Frontend**: HTML/CSS/JS (misma línea visual actual)
- **Backend**: Supabase (PostgreSQL + Storage + Auth)
- **Hosting**: dominio propio (portal.construccioneslosalmendros.cl) con deploy backend simple (Vercel/Railway) para funciones admin

---

## Autenticación (definitiva)
- Login por **email + password** (no Google).
- Solo **admin** crea accesos de clientes: ingresa email del cliente + password transitorio.
- Cliente autentica normalmente.
- **Primer ingreso**: flag `force_password_change = true` → el portal muestra pantalla de cambio de contraseña obligatorio antes de acceder al panel.
- **Recuperar clave**: flujo nativo de Supabase "Forgot password" por email.

---

## 1) Bases de datos

Ver `supabase/migrations/001_initial_schema.sql`.

### Tablas
- `clients` — datos del cliente
- `projects` — obra, estado fechas, presupuesto
- `documents` — archivos por proyecto (tipo, path storage)
- `payments` — cobros, estados y comprobantes
- `progress_events` — hitos de avance
- `photos` — imágenes por hito
- `app_users` — mapeo auth + rol + flag cambio password
- `staff_audit` — registro de acciones sensibles (creación/edición pagos, usuarios)

---

## 2) Lógica de negocios

### Pagos
- Estado visible: `pendiente`, `pagado`, `atrasado`.
- Regla: `atrasado` se infiere en la query cuando `status = pendiente` y `due_date < hoy`.
- **Staff**:
  - “Por cobrar”: `where status in ('pendiente', 'atrasado') order by due_date asc`.
  - Resumen por proyecto: total pagado, saldo, atrasados.
- **Cliente**:
  - Listado filtrado a sus proyectos, solo lectura.

### Avance
- Timeline ordenado por `event_date`.
- Fotos asociadas a eventos.

### Documentos
- Staff sube a Supabase Storage; cliente descarga/lee.
- Tipos: presupuesto, avance, plano, legal, otro.

---

## 3) Seguridad / RLS
- Cliente solo ve `client_id` propio.
- Staff ve todo (excepto gestión de usuarios).
- Admin ve y gestiona todo, incluida creación de usuarios.

---

## 4) Flujos clave

### Admin crea usuario cliente
1. Admin va a "Nuevo cliente" o "Dar acceso".
2. Ingresa: email, nombre, password transitorio.
3. Backend crea usuario en auth.users (service_role) y registra `app_users` con `force_password_change = true`.
4. Cliente recibe email de bienvenida/creación con credenciales.
5. Cliente ingresa → portal detecta flag y fuerza cambio de contraseña.

### Cliente recupera contraseña
1. Cliente hace clic en "Olvidé mi contraseña".
2. Ingresa email.
3. Supabase envía link de reseteo.
4. Cliente define nueva clave.
5. El portal, después del cambio exitoso, limpia el flag `force_password_change`.

---

## 5) Despliegue / hosting
- Landing actual: GitHub Pages (sin cambios).
- Portal: frontend en mismos assets, protegido con redirect en hosting; backend mínimo para crear usuarios (Edge Function o serverless) usando `service_role`.
- Dominio sugerido: `portal.construccioneslosalmendros.cl`.

---

## 6) Criterios de éxito
- [ ] Admin crea usuario cliente con password provisorio en < 5 min.
- [ ] Cliente ingresa, cambia contraseña obligatorio y accede a su panel.
- [ ] Cliente ve solo sus proyectos, avances, documentos y pagos.
- [ ] Staff ve resumen de cobros con atrasos sin intervención manual.

---

## Próxima acción sugerida
1. Crear proyecto en Supabase.
2. Correr `supabase/migrations/001_initial_schema.sql` en el SQL Editor.
3. Correr seed de admin.
4. Conectar frontend a Supabase y armar login básico.
