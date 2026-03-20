# 📊 ERP Contable - Atlas Digital Tech

Sistema de Planificación de Recursos Empresariales (ERP) diseñado para escalar y automatizar la gestión financiera, contable y tributaria de las pymes en Chile.

![Estado](https://img.shields.io/badge/Estado-Beta_1.0_Cerrada-success)
![Frontend](https://img.shields.io/badge/Frontend-React_+_Vite-blue)
![Backend](https://img.shields.io/badge/Backend-PHP_8.2-777BB4)
![Database](https://img.shields.io/badge/Database-MySQL_8.0-orange)

## 🏢 Arquitectura del Proyecto

El proyecto está dividido en un frontend reactivo (Single Page Application) y una API RESTful en el backend.

```text
/ (Raíz del Proyecto)
├── .github/                  # Configuración de GitHub Actions (Pipelines CI/CD)
├── Backend/                  # API RESTful nativa
│   ├── App/
│   │   ├── Config/           # Conexión a BD, Router y Variables de Entorno (.env)
│   │   ├── Controllers/      # Controladores de la API (Endpoints)
│   │   ├── Helpers/          # Utilidades (JWT, Mailer, Fechas)
│   │   ├── Middlewares/      # Filtros de seguridad (AuthMiddleware)
│   │   ├── Repositories/     # Capa de acceso a datos (Consultas SQL PDO)
│   │   └── Services/         # Lógica de negocio core
│   ├── Public/               # Punto de entrada (index.php, Cabeceras de Seguridad)
│   ├── Tests/                # Pruebas Unitarias y de Integración (PHPUnit)
│   ├── vendor/               # Dependencias de Composer
│   └── phpstan.neon          # Configuración de análisis estático
├── Frontend/                 # Aplicación Web React
│   ├── public/               # Assets estáticos (Favicon, logos)
│   ├── src/                  # Componentes, Hooks, Contextos y Vistas
│   ├── package.json          # Dependencias NPM
│   └── vite.config.js        # Configuración del empaquetador
└── Base de Datos/            # Estructura SQL y datos semilla para Testing/CI
```

## 🚀 Módulos Principales

* **Seguridad (RBAC):** Control de Acceso Basado en Roles. Los menús y componentes de la UI reaccionan a la matriz de permisos configurada desde la base de datos.
* **Finanzas y Tesorería:** Importación de cartolas bancarias, conciliación inteligente, pagos masivos y gestión de anticipos.
* **Contabilidad Avanzada:** Generación automática de asientos, Libro Mayor, Libro Diario y mantenedor de Plan de Cuentas.
* **Activos Fijos:** Cálculo automatizado de depreciación mensual y costeo de proyectos en construcción.
* **Cumplimiento Tributario:** Simulación de Formulario 29 (F29) y pre-cálculo para la Operación Renta Anual.

## 🛠️ Entorno de Desarrollo (Local)

### Prerrequisitos
* PHP 8.2 o superior
* Node.js 22 o superior
* MySQL 8.0
* Composer

### Configuración del Backend
1. Navegar a la carpeta `Backend/`
2. Instalar dependencias: `composer install`
3. Duplicar `.env.example` a `.env` y configurar credenciales de BD.
4. Levantar servidor local en el puerto 8002:

```bash
php -S localhost:8002 -t Public
```

## Configuración del Frontend
1. Navegar a la carpeta Frontend/
2. Instalar dependencias: npm install
3. Levantar entorno de desarrollo de Vite (Puerto 8001):

```bash
npm run dev
```
## 🔄 Integración y Despliegue Continuo (CI/CD)
* Este repositorio cuenta con un pipeline automatizado vía GitHub Actions (ci-cd.yml).
* Push a dev: Ejecuta entorno MySQL en contenedor, análisis estático con PHPStan (Nivel 5) y suite completa de PHPUnit.
* Push a main: Ejecuta pruebas de calidad y, si son exitosas, compila el Frontend de React y despliega ambos ecosistemas a producción mediante FTP seguro.
