# alxarafe/resource-controller

![PHP Version](https://img.shields.io/badge/PHP-8.2+-blueviolet?style=flat-square)
![CI](https://github.com/alxarafe/resource-controller/actions/workflows/ci.yml/badge.svg)
![Tests](https://github.com/alxarafe/resource-controller/actions/workflows/tests.yml/badge.svg)
![Static Analysis](https://img.shields.io/badge/static%20analysis-PHPStan%20%2B%20Psalm-blue?style=flat-square)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/alxarafe/resource-controller/issues)

**ORM-agnostic declarative CRUD controller for PHP.**

Auto-generates list views, edit forms, filters, and actions from field metadata — without coupling to any specific ORM, template engine, or framework.

## Features

- 🏗️ **Declarative**: Define fields and columns, get full CRUD automatically
- 🔌 **ORM-Agnostic**: Works with Eloquent, Doctrine, PDO, REST APIs, or any data source
- 🎨 **UI Components**: 15 field types, panels, tabs, filters — all serializable to JSON
- 🪝 **Hook System**: Extensible lifecycle (before/after save, form field injection)
- 🌍 **i18n Ready**: Pluggable translator contract
- 📦 **Zero Dependencies**: Only requires PHP 8.2

## Ecosystem

This package is the core of the Alxarafe Resource ecosystem. Use it with the adapters that fit your stack:

| Package | Purpose | Status |
|---|---|---|
| **[resource-controller](https://github.com/alxarafe/resource-controller)** | Core CRUD engine + UI components | ✅ Stable |
| **[resource-eloquent](https://github.com/alxarafe/resource-eloquent)** | Eloquent ORM adapter (Repository, Query, Transaction) | ✅ Stable |
| **[resource-blade](https://github.com/alxarafe/resource-blade)** | Blade template renderer adapter | 🚧 Coming soon |
| **[resource-twig](https://github.com/alxarafe/resource-twig)** | Twig template renderer adapter | 🚧 Coming soon |

## Installation

```bash
composer require alxarafe/resource-controller
```

For Eloquent support:
```bash
composer require alxarafe/resource-eloquent
```

For Blade rendering:
```bash
composer require alxarafe/resource-blade
```

For Twig rendering:
```bash
composer require alxarafe/resource-twig
```

## Quick Start

```php
use Alxarafe\ResourceController\AbstractResourceController;
use Alxarafe\ResourceController\Contracts\RepositoryContract;
use Alxarafe\ResourceController\Component\Fields\Text;
use Alxarafe\ResourceController\Component\Fields\Decimal;
use Alxarafe\ResourceController\Component\Fields\Boolean;

class ProductController extends AbstractResourceController
{
    public static function getModuleName(): string { return 'Shop'; }
    public static function getControllerName(): string { return 'Product'; }
    public static function url(string $action = 'index', array $params = []): string
    {
        return '/products' . ($action !== 'index' ? "/{$action}" : '');
    }

    protected function getRepository(string $tabId = 'default'): RepositoryContract
    {
        return new EloquentRepository(Product::class); // or any adapter
    }

    protected function getListColumns(): array
    {
        return [
            new Text('name', 'Name'),
            new Decimal('price', 'Price', ['min' => 0]),
            new Boolean('active', 'Active'),
        ];
    }

    protected function getEditFields(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'fields' => [
                    new Text('name', 'Name', ['required' => true]),
                    new Decimal('price', 'Price'),
                    new Boolean('active', 'Active'),
                ],
            ],
        ];
    }
}
```

## Architecture

```
┌──────────────────────────────────────────────┐
│         Your Controller                       │
│  getRepository() → RepositoryContract         │
│  getListColumns() → Field[]                   │
│  getEditFields()  → Field[]                   │
├──────────────────────────────────────────────┤
│         ResourceTrait (this package)          │
│  buildConfiguration()                         │
│  handleRequest()                              │
│  fetchListData() / saveRecord()               │
├──────────────────────────────────────────────┤
│         Contracts                             │
│  RepositoryContract  TranslatorContract       │
│  QueryContract       MessageBagContract       │
│  TransactionContract HookContract             │
│  RendererContract                             │
└──────────────────────────────────────────────┘
         ↓ implemented by ↓
┌──────────────┐ ┌──────────────┐ ┌────────────┐
│ Eloquent     │ │ Blade        │ │ Twig       │
│ Adapter      │ │ Adapter      │ │ Adapter    │
└──────────────┘ └──────────────┘ └────────────┘
```

## Contracts

| Contract | Purpose | Null Default |
|---|---|---|
| `RepositoryContract` | Data access (CRUD + query) | — (must implement) |
| `QueryContract` | Fluent query builder | — (from Repository) |
| `TransactionContract` | DB transactions | `NullTransaction` |
| `TranslatorContract` | i18n / translations | `NullTranslator` |
| `MessageBagContract` | Flash messages | `NullMessageBag` |
| `HookContract` | Plugin extensibility | `NullHookService` |
| `RendererContract` | Template rendering | — (optional) |
| `RelationContract` | Parent-child sync | — (optional) |

## Development

### Docker

```bash
docker compose up -d
docker exec alxarafe-resources composer install
```

### Running the CI pipeline locally

```bash
bash bin/ci_local.sh
```

This runs, in order: PHPCBF → PHPCS → PHPStan → Psalm → PHPUnit.

### Running tests only

```bash
bash bin/run_tests.sh
```

## License

GPL-3.0-or-later
