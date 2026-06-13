# 📊 Tenri ERP Cloud

Sistema de Planificación de Recursos Empresariales (ERP) diseñado para escalar y automatizar la gestión financiera, contable, tributaria y de remuneraciones de las pymes en Chile.

![Estado](https://img.shields.io/badge/Estado-Beta_1.0_Cerrada-success)
![Frontend](https://img.shields.io/badge/Frontend-React_19_+_Vite-blue)
![Backend](https://img.shields.io/badge/Backend-Laravel_12_(PHP_8.2)-777BB4)
![Database](https://img.shields.io/badge/Database-MySQL_8.0-orange)

## 🏢 Arquitectura del Proyecto

Monorepo (pnpm workspace) con una SPA en React y una API RESTful en Laravel 12.

```text
/ (Raíz del Proyecto)
├── .github/workflows/        # CI/CD (ci-cd.yml: tests + deploy, e2e.yml: Playwright)
├── Backend-laravel/           # API RESTful Laravel 12
│   ├── app/
│   │   ├── Domains/           # Código organizado por dominio de negocio
│   │   │   ├── Core/          # Auth, usuarios, roles, empresas, suscripciones
│   │   │   ├── Contabilidad/  # Plan de cuentas, asientos, períodos, reportes
│   │   │   ├── Comercial/     # Clientes, proveedores, facturas, cotizaciones
│   │   │   ├── Tesoreria/     # Bancos, conciliación, pagos
│   │   │   ├── Rrhh/          # Empleados, contratos, liquidaciones, Previred
│   │   │   ├── Inventario/    # Productos, bodegas, kardex, picking/packing
│   │   │   ├── Sii/           # Integración Servicio de Impuestos Internos
│   │   │   ├── Activos/       # Activos fijos y depreciación
│   │   │   └── CorreccionMonetaria/
│   │   ├── Http/Middleware/   # RBAC, suscripciones, API key HMAC
│   │   └── Support/           # Utilidades transversales (firma HMAC, etc.)
│   ├── routes/api.php         # Todas las rutas de la API
│   ├── tests/                 # PHPUnit (Unit + Feature)
│   └── phpunit.xml            # Tests contra SQLite en memoria por defecto
├── Frontend/                  # Aplicación Web React 19
│   ├── src/
│   │   ├── Modulos/           # Vistas por módulo de negocio
│   │   ├── Contextos/         # AuthContext, Permisos (RBAC)
│   │   ├── Configuracion/     # Cliente axios central, logger
│   │   ├── Componentes/       # Componentes compartidos
│   │   └── Utilidades/        # Helpers (export Excel/PDF)
│   ├── e2e/                   # Tests E2E Playwright
│   └── vite.config.js
├── Base de Datos/             # Estructura SQL y datos semilla
└── docs/                      # Normativa SII, leyes RRHH, auditorías
```

## 🚀 Módulos Principales

* **Seguridad (RBAC):** Control de Acceso Basado en Roles con permisos granulares por endpoint. Los menús y componentes de la UI reaccionan a la matriz de permisos.
* **Multitenancy:** Aislamiento de datos por empresa (`empresa_id`) mediante global scope de Eloquent.
* **Finanzas y Tesorería:** Importación de cartolas bancarias, conciliación inteligente, pagos masivos y gestión de anticipos.
* **Contabilidad Avanzada:** Generación automática de asientos, Libro Mayor, Libro Diario, mantenedor de Plan de Cuentas y bloqueo de períodos.
* **RRHH y Remuneraciones:** Contratos, liquidaciones de sueldo, finiquitos, parámetros previsionales (AFP, salud, CCAF), archivo Previred y centralización contable.
* **Inventario:** Multi-bodega, kardex valorizado, lotes, reservas, picking/packing y tomas físicas.
* **Activos Fijos:** Cálculo automatizado de depreciación mensual.
* **Cumplimiento Tributario:** Formulario 29 (F29), F22 y pre-cálculo para la Operación Renta.
* **Suscripciones:** SSO e integración con tenri.cl; gating de funcionalidades por plan y estado de suscripción.

## 🛠️ Entorno de Desarrollo (Local)

### Prerrequisitos
* PHP 8.2 o superior + Composer
* Node.js 22 o superior + pnpm 11
* MySQL 8.0 (opcional en local: los tests usan SQLite)

### Backend
```bash
cd Backend-laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve          # API en http://localhost:8000
# o todo junto (serve + queue + logs + vite):
composer dev
```

### Frontend
```bash
cd Frontend
pnpm install
pnpm dev                   # Vite dev server
```

### Tests
```bash
# Backend (SQLite en memoria, ~12s)
cd Backend-laravel && php artisan test

# Frontend
cd Frontend && pnpm lint && pnpm test    # ESLint + Vitest
pnpm e2e                                  # Playwright (requiere pnpm e2e:install)
```

## 🔄 Integración y Despliegue Continuo (CI/CD)

Pipeline automatizado vía GitHub Actions (`.github/workflows/ci-cd.yml`):

* **Push a cualquier rama:** suite PHPUnit contra SQLite **y** contra MySQL 8 en contenedor (los tests deben pasar en ambos engines), más lint y build del frontend.
* **Push a `main`:** si todos los tests pasan, compila el frontend y despliega ambos ecosistemas a producción (FTP + SSH con migraciones y cache de Laravel).
