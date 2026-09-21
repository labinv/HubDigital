<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base para integraciones que requieren el esquema completo de PostgreSQL.
 *
 * El proceso de integración prepara una base efímera con `artisan migrate`
 * antes de Pest y la destruye al acabar. Se mantiene separada de las pruebas
 * de infraestructura y no incorpora adaptadores SQLite ni RefreshDatabase.
 */
abstract class PostgresIntegrationTestCase extends TestCase
{
    use DatabaseTransactions;
}
