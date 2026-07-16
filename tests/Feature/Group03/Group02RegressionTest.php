<?php

it('keeps the group 02 archive schema route available', function (): void {
    expect(app('router')->getRoutes()->getByName('admin.archive-schema'))->not->toBeNull();
});

it('keeps registration blocked', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});
