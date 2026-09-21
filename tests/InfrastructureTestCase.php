<?php

namespace Tests;

/**
 * Base de pruebas de infraestructura que arrancan Laravel pero no requieren
 * tablas. No incorpora RefreshDatabase: los adaptadores R2 y los temporales se
 * prueban sin ejecutar migraciones de esquemas PostgreSQL en SQLite.
 */
abstract class InfrastructureTestCase extends TestCase
{
}
