<?php

test('the application returns a successful response', function () {
    $response = $this->get('/dashboard');

    $response->assertSuccessful();
});
