<?php

it('blocks public registration', function (): void {
    $this->get(route('register'))->assertNotFound();
    $this->post(route('register'))->assertNotFound();
});
