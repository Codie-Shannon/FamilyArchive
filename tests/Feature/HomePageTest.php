<?php

test('the home page is available', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});
