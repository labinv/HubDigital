<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base para pruebas funcionales que usan el esquema PostgreSQL completo.
 *
 * El validador prepara desde cero la base aislada hubdigital_test una sola vez.
 * Cada prueba usa una transacción y la revierte al terminar, evitando que
 * RefreshDatabase deje tablas parciales en los esquemas PostgreSQL no públicos.
 */
abstract class DatabaseFeatureTestCase extends TestCase
{
    use DatabaseTransactions;
}
