# Validador - Paquete Laravel para Validación Enterprise

Paquete Composer reutilizable con **Clean Architecture** para validar esquemas de base de datos Oracle, variables de entorno y estado de git. Compatible con Laravel 4.2, 7, 9 y 13.

[![Latest Version](https://img.shields.io/packagist/v/validador/core.svg)](https://packagist.org/packages/validador/core)
[![License](https://img.shields.io/packagist/l/validador/core.svg)](LICENSE.md)

## 🎯 Características

- ✅ **Validación de esquemas Oracle**: Tablas, columnas, índices, constraints, stored procedures
- ✅ **Validación de variables de entorno**: Compara `.env` entre ambientes, detecta variables sensibles
- ✅ **Validación de Git**: Verifica branch limpia, commits requeridos, tags
- ✅ **Clean Architecture**: Interfaces, inyección de dependencias, testable
- ✅ **Multi-versión Laravel**: Compatible con 4.2, 7, 9, 13
- ✅ **PHP 5.6+**: Funciona desde versiones antiguas hasta PHP 8.3
- ✅ **Bearer Token Auth**: Middleware incluido con fallback para Apache/CGI
- ✅ **OpenAPI Ready**: Documentación automática de endpoints

## 📦 Instalación

```bash
composer require validador/core:dev-validador
```

### Configuración del repositorio VCS

Como el paquete no está en Packagist, agrega el repositorio en tu `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://git.mds.cl/publico/packages/rsh/validador-rfc.git"
        }
    ],
    "require": {
        "validador/core": "dev-validador"
    }
}
```

## 🚀 Uso Básico

### 1. Registrar el ServiceProvider

**Laravel 5.5+** (auto-discovery):
```php
// No necesita registro manual
```

**Laravel 4.2**:
```php
// app/config/app.php
'providers' => array(
    'Vendor\\Validator\\Providers\\ValidatorServiceProvider',
),
```

### 2. Publicar configuración

```bash
# Laravel 5+
php artisan vendor:publish --provider="Vendor\\Validator\\Providers\\ValidatorServiceProvider" --tag="validator-config"

# Laravel 4.2
php artisan config:publish vendor/validator --path vendor/validador/core/config
```

### 3. Configurar variables de entorno

```env
VALIDATOR_API_TOKEN=your-secure-api-token
VALIDATOR_DEFAULT_CONNECTION=oracle
```

### 4. Configurar conexiones Oracle

```php
// config/database.php
'connections' => [
    'oracle' => [
        'driver' => 'oracle',
        'host' => env('DB_HOST'),
        'port' => env('DB_PORT', 1521),
        'service_name' => env('DB_SERVICE_NAME'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => env('DB_CHARSET', 'AL32UTF8'),
    ],
],
```

## 🏗️ Arquitectura

### Clean Architecture

```
src/
├── Adapters/           # Adaptadores de infraestructura
│   ├── JsonLoader.php
│   ├── EnvironmentProvider.php
│   ├── EnvFileProvider.php
│   └── ConnectionResolver.php
├── Contracts/          # Interfaces (Inyección de dependencias)
│   ├── ValidatorInterface.php
│   ├── OracleRepositoryInterface.php
│   ├── JsonLoaderInterface.php
│   ├── EnvironmentProviderInterface.php
│   └── ConnectionResolverInterface.php
├── DTO/                # Data Transfer Objects
│   └── ValidationResult.php
├── Exceptions/         # Excepciones personalizadas
│   └── ValidationException.php
├── Middleware/         # Bearer Token Auth
│   └── BearerTokenMiddleware.php
├── Oracle/             # Repositorio Oracle
│   └── OracleRepository.php
├── Providers/          # Service Provider
│   └── ValidatorServiceProvider.php
├── Services/           # Lógica de negocio
│   └── ValidatorService.php
└── Validators/         # Validadores específicos
    ├── DatabaseValidator.php
    ├── EnvValidator.php
    └── GitValidator.php
```

### Patrones de Diseño

#### Repository Pattern
```php
interface OracleRepositoryInterface {
    public function getTables($schema);
    public function getColumns($schema, $table);
    public function getPrimaryKeys($schema, $table);
    public function getForeignKeys($schema, $table);
    public function getIndexes($schema, $table);
    public function getDdl($objectType, $schema, $objectName);
    // ... 20+ métodos
}

class OracleRepository implements OracleRepositoryInterface {
    public function __construct(
        private ConnectionResolverInterface $resolver,
        private string $connection = 'oracle'
    ) {}
}
```

#### Strategy Pattern
```php
interface ValidatorInterface {
    public function getName(): string;
    public function validate(array $options = []): ValidationResult;
}

class DatabaseValidator implements ValidatorInterface { }
class EnvValidator implements ValidatorInterface { }
class GitValidator implements ValidatorInterface { }
```

#### DTO Pattern
```php
class ValidationResult {
    public bool $valid;
    public array $errors;
    public array $warnings;
    public array $info;
    
    public function merge(self $other): self { }
}
```

## 📡 API Endpoints

### Validar esquema completo

```http
POST /api/validate
Authorization: Bearer {token}
Content-Type: application/json

{
    "schema": "ASIGNACION",
    "tables": ["personas", "grupo_familiar"],
    "validate_indexes": true,
    "validate_constraints": true
}
```

**Respuesta:**
```json
{
    "valid": true,
    "errors": [],
    "warnings": [
        "Table 'personas' has no primary key"
    ],
    "info": [
        "Validated 2 tables",
        "Checked 15 columns",
        "Found 3 indexes"
    ]
}
```

### Validar variables de entorno

```http
POST /api/validate
Authorization: Bearer {token}
Content-Type: application/json

{
    "validator": "env",
    "environment": "production",
    "compare_with": ".env.example"
}
```

### Validar estado Git

```http
POST /api/validate
Authorization: Bearer {token}
Content-Type: application/json

{
    "validator": "git",
    "required_branch": "main",
    "require_clean": true,
    "require_tag": true
}
```

## 🔐 Autenticación

### BearerTokenMiddleware

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'validator.auth' => \Vendor\Validator\Middleware\BearerTokenMiddleware::class,
];

// routes/api.php
Route::middleware('validator.auth')->group(function () {
    Route::post('/validate', [ValidateController::class, 'validate']);
});
```

**Mecanismos de autenticación:**
1. Header estándar: `Authorization: Bearer {token}`
2. Header personalizado: `X-Validator-Token: {token}` (fallback para Apache/CGI)

## 🧪 Testing

```bash
# Ejecutar tests
vendor/bin/phpunit

# Con cobertura
vendor/bin/phpunit --coverage-html coverage

# Tests específicos
vendor/bin/phpunit --filter DatabaseValidatorTest
```

**Cobertura de tests:**
- 11 archivos de tests unitarios
- PHPUnit + Mockery
- SQLite in-memory para tests rápidos
- Mock de conexiones Oracle

## 📊 Validaciones Disponibles

### Database Validator
- ✅ Existencia de tablas
- ✅ Estructura de columnas (tipo, nullable, default)
- ✅ Primary keys
- ✅ Foreign keys
- ✅ Índices
- ✅ Stored procedures
- ✅ Functions
- ✅ Sequences
- ✅ Views
- ✅ Triggers
- ✅ Packages
- ✅ Synonyms
- ✅ Constraints (CHECK, UNIQUE, NOT NULL)

### Environment Validator
- ✅ Variables requeridas por ambiente
- ✅ Comparación entre `.env` files
- ✅ Detección de variables sensibles
- ✅ Validación de formatos (URL, email, IP)
- ✅ Auto-discovery de variables no documentadas

### Git Validator
- ✅ Branch actual es la esperada
- ✅ Working tree limpia (sin cambios sin commit)
- ✅ Tags/branches requeridos existen
- ✅ Commits ahead/behind tracking

## 🎨 Configuración Avanzada

### Configurar múltiples esquemas

```php
// config/validator.php
return [
    'connections' => [
        'oracle' => [
            'schemas' => ['ASIGNACION', 'EXPORT'],
        ],
        'oracle_export' => [
            'schemas' => ['ASIGNACION_EXPORT'],
        ],
    ],
    
    'validators' => [
        'database' => [
            'enabled' => true,
            'auto_discover' => true,
        ],
        'env' => [
            'enabled'