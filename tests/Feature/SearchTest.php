<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

dataset('searchable models', [
    'permission' => [Permission::class, ['name' => 'post.edit'], 'POST.EDIT'],
    'role' => [Role::class, ['name' => 'editor'], 'EDITOR'],
    'user by name' => [User::class, ['name' => 'Jane Doe', 'email' => 'jane@example.test', 'password' => 'x'], 'jane doe'],
    'user by email' => [User::class, ['name' => 'Jane Doe', 'email' => 'jane@example.test', 'password' => 'x'], 'JANE@EXAMPLE'],
]);

test('search matches regardless of case', function (string $model, array $attributes, string $term) {
    $record = $model::create($attributes);

    expect($model::search($term)->pluck('id'))->toContain($record->id);
})->with('searchable models');

test('search compiles to ILIKE on Postgres, where LIKE is case-sensitive', function (string $model) {
    // toSql() only needs the grammar; no Postgres server is contacted.
    $sql = $model::on('pgsql')->search('x')->toSql();

    expect($sql)->toContain('ilike')
        ->not->toMatch('/\blike\b/');
})->with([Permission::class, Role::class, User::class]);

test('user search covers both name and email', function () {
    $sql = User::search('x')->toSql();

    expect($sql)->toContain('"name"')->toContain('"email"');
});

test('a blank term leaves the query unfiltered', function (?string $term) {
    expect(Role::search($term)->toSql())->toBe(Role::query()->toSql());
})->with([null, '', '   ']);

test('search terms are bound, not interpolated', function () {
    $query = User::search("o'brien");

    expect($query->toSql())->not->toContain("o'brien")
        ->and($query->getBindings())->toContain("%o'brien%");
});
