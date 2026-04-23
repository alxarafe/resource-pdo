# alxarafe/resource-pdo

![PHP Version](https://img.shields.io/badge/PHP-8.2+-blueviolet?style=flat-square)
![CI](https://github.com/alxarafe/resource-pdo/actions/workflows/ci.yml/badge.svg)
![Tests](https://github.com/alxarafe/resource-pdo/actions/workflows/tests.yml/badge.svg)
![Static Analysis](https://img.shields.io/badge/static%20analysis-PHPStan%20%2B%20Psalm-blue?style=flat-square)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/alxarafe/resource-pdo/issues)

**Adaptador nativo PDO para alxarafe/resource-controller.**

Implementa `RepositoryContract`, `QueryContract` y `TransactionContract` usando PHP PDO nativo, sin depender de ORMs ni librerías pesadas de base de datos.

## Ecosistema

| Paquete | Propósito | Estado |
|---|---|---|
| **[resource-controller](https://github.com/alxarafe/resource-controller)** | Motor CRUD central + componentes UI | ✅ Estable |
| **[resource-eloquent](https://github.com/alxarafe/resource-eloquent)** | Adaptador ORM Eloquent | ✅ Estable |
| **[resource-pdo](https://github.com/alxarafe/resource-pdo)** | Adaptador nativo PDO | ✅ Estable |
| **[resource-blade](https://github.com/alxarafe/resource-blade)** | Adaptador de renderizado con Blade | ✅ Estable |
| **[resource-twig](https://github.com/alxarafe/resource-twig)** | Adaptador de renderizado con Twig | ✅ Estable |
| **[resource-html](https://github.com/alxarafe/resource-html)** | Adaptador de renderizado con plantillas PHP/HTML | ✅ Estable |

## Instalación

```bash
composer require alxarafe/resource-pdo
```

## Uso

```php
use Alxarafe\ResourceController\AbstractResourceController;
use Alxarafe\ResourceController\Contracts\RepositoryContract;
use Alxarafe\ResourceController\Contracts\TransactionContract;
use Alxarafe\ResourcePdo\PdoRepository;
use Alxarafe\ResourcePdo\PdoTransaction;

class UsersController extends AbstractResourceController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    protected function getRepository(string $tabId = 'default'): RepositoryContract
    {
        return new PdoRepository($this->pdo, 'users', 'id');
    }

    protected function getTransaction(): TransactionContract
    {
        return new PdoTransaction($this->pdo);
    }
}
```

## Licencia

GPL-3.0-or-later
