<?php

test('the root redirects to the public portal', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/portal');
});
