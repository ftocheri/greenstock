<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/products')->assertRedirect('/login');
    }

    public function test_it_lists_products_with_computed_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Heirloom Tomato Seed Packet']);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 40,
        ]);
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 15,
        ]);

        $response = $this->actingAs($user)->get('/products');

        $response->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->where('products.data.0.current_stock', 25)
        );
    }

    public function test_search_filters_by_name_or_sku(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Basil Seed Packet', 'sku' => 'BS-0001']);
        Product::factory()->create(['name' => 'Zucchini Starter Plant', 'sku' => 'ZS-0002']);

        $response = $this->actingAs($user)->get('/products?search=Basil');

        $response->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'BS-0001')
        );
    }
}
