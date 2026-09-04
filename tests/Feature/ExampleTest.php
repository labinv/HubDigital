<?php

test('la raiz muestra el portal publico del laboratorio', function (): void {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('Ciencia, colecciones y biodiversidad del Ecuador')
        ->assertSee('Laboratorio de Invertebrados');
});
