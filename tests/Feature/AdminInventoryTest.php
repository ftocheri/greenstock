<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_admin_inventory(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.inventory.index'))->assertForbidden();
    }

    public function test_non_admin_cannot_adjust_stock(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.inventory.adjust', $product), ['delta' => 5, 'reason' => 'test'])
            ->assertForbidden();
    }

    public function test_admin_can_adjust_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.inventory.adjust', $product), [
            'delta' => 15,
            'reason' => 'Cycle count correction',
        ]);

        $response->assertRedirect();
        $this->assertSame(15, $product->currentStock());
    }

    public function test_adjustment_requires_a_non_zero_delta_and_a_reason(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', $product), ['delta' => 0, 'reason' => ''])
            ->assertSessionHasErrors(['delta', 'reason']);
    }
}
