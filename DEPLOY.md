# Deploy del Portal a Producción
## Portal Clientes — Construcciones Los Almendros

---

## 1. Requisitos de hosting

| Requisito | Especificación |
|---|---|
| **PHP** | 8.1+ (con extensiones: PDO, SQLite3, session, json, mbstring) |
| **Base de datos** | SQLite (archivo local, sin servidor MySQL) |
| **Servidor web** | Apache con mod_rewrite o Nginx |
| **Almacenamiento** | Uploads a `/uploads/` (necesita permisos de escritura) |
| **HTTPS** | Obligatorio (certificado Let's Encrypt o similar) |

**Alojamientos que funcionan bien con PHP + SQLite:**
- Hostinger (PHP + SQLite)
- DonWeb / Neolo (planes compartidos con PHP)
- Railway / Render (para deploy más moderno)
- Un VPS básico (DigitalOcean, Vultr, etc.)

---

## 2. Estructura del proyecto (web root)

```
portal-almendros/
├── public/          ← APUNTAR EL DOMINIO AQUÍ (web root)
│   ├── app.php
│   ├── login.php
│   ├── logout.php
│   ├── .htrouter.php
│   └── assets/
├── api/             ← LÓGICA DE NEGOCIO (no accesible desde web)
│   ├── config.php
│   ├── db.php
│   ├── projects.php
│   ├── proyectos.php
│   ├── ...
│   └── portal.db    ← BASE DE DATOS SQLITE
├── uploads/         ← ARCHIVOS SUBIDOS (permisos 755 / www-data)
├── scripts/
└── .gitignore
```

**Importante:** El `public/` es el document root. Los archivos de `api/` NO deben ser accesibles directamente desde el navegador. Con Apache, agregar en `public/.htaccess`:

```apache
RewriteEngine On
RewriteRule ^api/ - [F,L]
```

---

## 3. Configuración del subdominio `portal.constructoralosalmendros.cl`

### Opción A: Apache (recomendada si el hosting lo permite)

```apache
<VirtualHost *:443>
    ServerName portal.constructoralosalmendros.cl
    DocumentRoot /ruta/a/portal-almendros/public
    
    <Directory /ruta/a/portal-almendros/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/portal.constructoralosalmendros.cl/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/portal.constructoralosalmendros.cl/privkey.pem
</VirtualHost>
```

### Opción B: Nginx

```nginx
server {
    listen 443 ssl;
    server_name portal.constructoralosalmendros.cl;
    root /ruta/a/portal-almendros/public;
    
    index login.php;
    
    ssl_certificate /etc/letsencrypt/live/portal.constructoralosalmendros.cl/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/portal.constructoralosalmendros.cl/privkey.pem;
    
    location / {
        try_files $uri $uri/ /login.php?$args;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 4. Pasos de deploy

```bash
# 1. Clonar el repositorio en el servidor
git clone https://github.com/joago99/portal-almendros.git /ruta/destino
cd /ruta/destino

# 2. Configurar permisos
chmod 755 uploads/
chmod 644 api/portal.db   # si ya existe
chown -R www-data:www-data uploads/ api/portal.db

# 3. Ejecutar setup para crear/actualizar la base de datos
php scripts/setup.php --production

# 4. Verificar que funcione
curl -I https://portal.constructoralosalmendros.cl/login.php
```

---

## 5. Cambios necesarios en el código para producción

### `api/config.php`

```php
// Cambiar de false a true para HTTPS
'secure' => true,

// Opcional: fijar dominio
'domain' => '.constructoralosalmendros.cl',

// Quitar marcas de prueba
```

### `public/.htrouter.php`

Para producción con Apache, el archivo `.htaccess` maneja las rutas. El `.htrouter.php` solo es necesario para el servidor de desarrollo. Crear `public/.htaccess`:

```apache
RewriteEngine On

# Si el archivo existe, servirlo directamente
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Redirigir todo a login.php
RewriteRule ^(.*)$ login.php [QSA,L]
```

---

## 6. Datos iniciales

### Usuarios de producción (crear después del deploy)

| Usuario | Email | Rol | Notas |
|---|---|---|---|
| Admin | joago@losalmendros.cl | admin | Creado vía seed |
| Staff | falcon@losalmendros.cl | staff | Partner |
| Staff | tiguer@losalmendros.cl | staff | Partner |

### Clientes iniciales
Se crean desde el Admin del portal después del deploy.

---

## 7. Seguridad

- [ ] HTTPS configurado y forzado
- [ ] `config.php`: `secure => true`
- [ ] `.htaccess` bloqueando acceso a `/api/`
- [ ] Caducidad de usuarios asignada
- [ ] Contraseñas temporales forzadas a cambiar
- [ ] Backups automáticos de `api/portal.db`
- [ ] Rate limiting en login (recomendado: 5 intentos/minuto)

---

## 8. Backup

```bash
# Backup diario de la base de datos
cp api/portal.db backups/portal-$(date +%Y%m%d).db

# Restaurar
cp backups/portal-20260630.db api/portal.db
```

---

## 9. Integración con el sitio principal

El botón "Portal Clientes" en **constructoralosalmendros.cl** ya apunta a:

```
https://portal.constructoralosalmendros.cl/login.php
```

Solo queda:
1. Configurar el subdominio `portal.constructoralosalmendros.cl` en el DNS
2. Apuntarlo al servidor donde esté el portal
3. Subir el código

---

## 10. Verificación post-deploy

1. Abrir `https://portal.constructoralosalmendros.cl/login.php`
2. Iniciar sesión con credenciales admin
3. Crear cliente de prueba
4. Crear proyecto asociado
5. Crear pago
6. Subir documento
7. Verificar Admin → usuarios
8. Probar caducidad
9. Verificar enlace desde constructoralosalmendros.cl

---

## 11. Datos de prueba actuales (no migrar a producción)

La base de datos actual (`api/portal.db`) contiene datos de prueba que deben ser eliminados en producción. Ejecutar:

```bash
php -r "
\$db = new PDO('sqlite:api/portal.db');
\$db->exec('DELETE FROM payments');
\$db->exec('DELETE FROM documents');
\$db->exec('DELETE FROM projects');
\$db->exec('DELETE FROM clients WHERE id > 0');
echo 'Datos de prueba limpiados\\n';
"
```

O simplemente borrar `api/portal.db` y ejecutar `setup.php` para crear una base limpia con solo los usuarios seed.
