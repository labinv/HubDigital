<?php

test('la raiz muestra el portal publico del laboratorio', function (): void {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('Ciencia, colecciones y biodiversidad del Ecuador')
        ->assertSee('Laboratorio de Invertebrados')
        ->assertSee('Orcés&nbsp;V.', false)
        ->assertDontSee('Una infraestructura científica para la biodiversidad')
        ->assertDontSee('Una colección también es una herramienta educativa')
        ->assertDontSee('Equipo y responsabilidades')
        ->assertDontSee('Conservación y documentación de ejemplares de la colección científica.')
        ->assertDontSee('>Colecciones</a>', false);

    expect(substr_count($response->getContent(), '>Servicios</a>'))->toBe(1);
});
