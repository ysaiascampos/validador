# Basic Auth para el reporte del validador — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Proteger `validator/report` y `validator/report/pdf` con Basic Auth sin sesión, configurable por entorno.

**Architecture:** Un middleware propio del paquete lee usuario/clave de `validator.report.auth`, acepta `PHP_AUTH_USER`/`PHP_AUTH_PW` o el header `Authorization: Basic`, compara con `hash_equals` y responde 401 con `WWW-Authenticate` cuando falta o no coincide. En Laravel 5+ se pasa como middleware del grupo de rutas; en 4.2 se registra como route filter `validator.report.auth`.

**Tech Stack:** PHP 5.6+, Laravel 4.2 / 5+ / 13, PHPUnit.

**Spec:** conversación del 2026-11-19 (diseño aprobado por el usuario).

## Global Constraints

- Compatible con Laravel 4.2: sin helpers de 5.0 (`view()`, `config()`, etc.).
- Fail-closed: si `enabled` es true y faltan credenciales, 401.
- Comparación con `hash_equals`.
- No tocar el API: sigue protegido con Bearer.

## Review Focus

- Credenciales enviadas por header vs por `PHP_AUTH_*` (Apache/CGI descarta headers).
- 401 debe incluir `WWW-Authenticate` para que el navegador abra el diálogo.
- `/validator/report/pdf` debe quedar protegido igual que el HTML.
- No filtrar credenciales en logs ni en el mensaje de error.
- El reporte no debe quedar abierto si `auth.enabled` es true pero no hay credenciales.

---

### Task 1: Middleware `ReportBasicAuthMiddleware`

**Files:**
- Create: `src/Middleware/ReportBasicAuthMiddleware.php`
- Test: `tests/ReportBasicAuthMiddlewareTest.php`

**Interfaces:**
- `handle(Request $request, Closure $next)` → `Response|mixed`
- Constructor: `__construct($config = null)` (usa `app('config')` si es null)

- [ ] **Step 1: Escribir el test que falla** (casos: deshabilitado, sin credenciales, usuario malo, clave mala, correcto por `PHP_AUTH_*`, correcto por header, no configurado, cabeceras anti-caché)
- [ ] **Step 2: Ejecutar y verificar que falla**
- [ ] **Step 3: Implementar el middleware**
- [ ] **Step 4: Ejecutar y verificar que pasa**
- [ ] **Step 5: Commit**

### Task 2: Configuración `validator.report.auth`

**Files:**
- Modify: `config/validator.php`
- Modify: los 3 configs de las apps RSH

- [ ] **Step 1: Agregar el bloque `auth` con `env()`**
- [ ] **Step 2: Actualizar el comentario del bloque `report`**
- [ ] **Step 3: Commit**

### Task 3: Rutas protegidas

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: 5+ — anteponer `ReportBasicAuthMiddleware::class` al grupo del reporte**
- [ ] **Step 2: 4.2 — registrar el filter `validator.report.auth` y agregarlo a `before`**
- [ ] **Step 3: Verificar con `php -l`**
- [ ] **Step 4: Commit**

### Task 4: Documentación y despliegue

**Files:**
- Modify: `README.md`
- Modify: `servicios-rsh/.env.example`

- [ ] **Step 1: Documentar variables y requisito de HTTPS**
- [ ] **Step 2: Commit**

### Task 5: Descarga de PDF sin segundo prompt

**Files:**
- Create: `src/Services/ReportLinkSigner.php`
- Modify: `src/Controllers/ReportController.php` (`query()` firma el enlace)
- Modify: `src/Middleware/ReportBasicAuthMiddleware.php` (acepta firma solo en `/report/pdf`)
- Modify: `config/validator.php` (`auth.secret`, `download_ttl`)
- Test: `tests/ReportLinkSignerTest.php`, `tests/ReportBasicAuthMiddlewareTest.php`

- [x] **Step 1: Firmar `expires` con HMAC-SHA256 y validar con `hash_equals`**
- [x] **Step 2: Aceptar la firma únicamente en la ruta del PDF**
- [x] **Step 3: Firmar el enlace en el controlador y documentar TTL/secreto**
- [x] **Step 4: Verificar con lint y arnés de stubs (13 casos)**
- [x] **Step 5: Commit**
