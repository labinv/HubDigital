<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InfrastructureTestCase;
use Tests\PostgresIntegrationTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Los flujos funcionales que persisten datos usan una base aislada. Se
// enumeran expresamente para que una prueba nueva no ejecute DDL sin declarar
// su dependencia de PostgreSQL.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(
        'Feature/DashboardCuraduriaTest.php',
        'Feature/DashboardTest.php',
        'Feature/DepositosApiDocumentacionSeguraTest.php',
        'Feature/DepositosCincoPuntosTest.php',
        'Feature/DepositosPortalTest.php',
        'Feature/ExampleTest.php',
        'Feature/FlujoDepositoE2ETest.php',
        'Feature/FlujoDepositoPersistenciaE2ETest.php',
        'Feature/OperacionesDocumentalesDepositosTest.php',
        'Feature/PwaPushSubscriptionTest.php',
        'Feature/SolicitudFirmadaIntegridadTest.php',
    );

// Estas pruebas sólo cubren adaptadores y el directorio temporal. Arrancan el
// contenedor Laravel, pero no necesitan ni deben tocar una base de datos.
pest()->extend(InfrastructureTestCase::class)
    ->in(
        'Feature/AlmacenamientoDepositosTest.php',
        'Feature/DirectorioTemporalHubDigitalTest.php',
    );

// Las rutas que verifican R2 con relaciones reales se ejecutan contra una
// base PostgreSQL aislada y previamente migrada. No emplean SQLite ni hacen
// DDL por caso de prueba: el procedimiento de integración crea y elimina esa
// base efímera completa.
pest()->extend(PostgresIntegrationTestCase::class)
    ->in('Feature/RutasObjetosR2Test.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
