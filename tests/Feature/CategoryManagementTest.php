<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a category can be created, updated, and deleted', function () {
    $createResponse = $this->postJson(route('categories.store'), [
        'name' => 'Office Supplies',
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.name', 'Office Supplies')
        ->assertJsonPath('data.slug', 'office-supplies');

    $category = Category::query()->firstOrFail();

    $this->putJson(route('categories.update', $category), [
        'name' => 'Office Equipment',
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'office-equipment');

    $this->deleteJson(route('categories.destroy', $category))
        ->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});
