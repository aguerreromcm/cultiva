# Cultiva

Resumen de la estructura del proyecto y cómo ejecutar el backend.

## Estructura del proyecto

```
cultiva/
├── backend/                    # Aplicación PHP (MVC)
│   ├── App/
│   │   ├── config/             # Configuración (configuracion.ini)
│   │   ├── controllers/        # Controladores (Principal, CDC, Login, etc.)
│   │   ├── models/             # Modelos (Oracle DB, APIs)
│   │   └── views/              # Vistas PHP
│   ├── Core/                   # Núcleo MVC (App, Database, Controller, View)
│   ├── Jobs/                   # Tareas (sincronización, UDI/dólar, etc.)
│   ├── libs/                   # Librerías (mPDF, PhpSpreadsheet, etc.)
│   └── public/                 # Punto de entrada web
│       ├── index.php           # Entrada de la aplicación
│       ├── .htaccess           # Reescritura de URLs
│       ├── css/, js/, img/     # Assets
│       └── ...
└── README.md
```

- **Backend**: PHP con arquitectura MVC, base de datos **Oracle** (OCI) y uso de APIs (CDC, huellas, etc.).
- **Entrada web**: todo pasa por `backend/public/index.php` y el `.htaccess` reescribe las URLs.

## Cómo ejecutar el backend

### 1. Requisitos

- **PHP** con extensiones: `pdo_oci` (Oracle), `curl`, `mbstring`, etc.
- **Oracle Instant Client** (para conectar a la base Oracle).
- Servidor web (Apache o nginx) con PHP, o el servidor integrado de PHP.

### 2. Configuración

1. En `backend/App/config/` debe existir el archivo **`configuracion.ini`** (no solo `configuracion_requerida.ini`).
2. Puedes copiar la plantilla y completar los valores:

   - **database**: `SERVIDOR`, `PUERTO`, `USUARIO`, `PASSWORD`, `ESQUEMA` (por defecto `ESIACOM`).
   - **otros**: `URL_HUELLAS`, `URL_CDC`, y si usas CDC: `URL_CDC_TST`, `API_KEY`, `API_KEY_TST`, `USER_CDC`, `PASS_CDC`, `CERT_CDC`, `CERT_CULTIVA`, `PASS_CERT`.

3. No subas `configuracion.ini` con contraseñas al repositorio (añádelo a `.gitignore` si hace falta).

### 3. Servidor web

**Opción A – Apache**

- Document root: `backend/public`.
- Asegúrate de que `mod_rewrite` esté activo y que se permita `.htaccess`.

**Opción B – Servidor integrado de PHP**

Usa el script `router.php` para que las URLs funcionen igual que con Apache (evita ERR_TOO_MANY_REDIRECTS):

```bash
cd backend/public
php -S localhost:8080 router.php
```

Luego abre en el navegador: `http://localhost:8080`. La ruta por defecto redirige a `/Principal/`; sin sesión verás la pantalla de login en `/Login/`.

### 4. Base de datos

La aplicación espera una base **Oracle**. Sin un `configuracion.ini` válido y una base accesible, al cargar una página que use la DB verás un mensaje tipo “Sistema fuera de línea” (respuesta 503).

---

Si quieres, el siguiente paso puede ser revisar contigo los valores concretos de `configuracion.ini` o cómo tener Oracle/entorno listo en tu máquina.
