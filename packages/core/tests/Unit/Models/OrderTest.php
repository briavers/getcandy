<?php

namespace GetCandy\Tests\Unit\Models;

use DateTime;
use GetCandy\Models\Currency;
use GetCandy\Models\Order;
use GetCandy\Models\OrderLine;
use GetCandy\Models\ProductVariant;
use GetCandy\Models\Transaction;
use GetCandy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group getcandy.orders
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_make_an_order()
    {
        Currency::factory()->create([
            'default' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
        ]);

        $data = $order->getRawOriginal();

        $this->assertDatabaseHas((new Order())->getTable(), $data);
    }

    /** @test */
    public function order_has_correct_casting()
    {
        Currency::factory()->create([
            'default' => true,
        ]);

        $order = Order::factory()->create([
            'user_id'   => null,
            'placed_at' => now(),
            'meta'      => [
                'foo' => 'bar',
            ],
            'tax_breakdown' => [
                ['name' => 'VAT', 'percentage' => 20, 'total' => 200],
            ],
        ]);

        $this->assertIsObject($order->meta);
        $this->assertIsIterable($order->tax_breakdown);
        $this->assertInstanceOf(DateTime::class, $order->placed_at);
    }

    /** @test */
    public function can_create_lines()
    {
        Currency::factory()->create([
            'default' => true,
        ]);

        $order = Order::factory()->create([
            'user_id'   => null,
            'placed_at' => now(),
            'meta'      => [
                'foo' => 'bar',
            ],
            'tax_breakdown' => [
                ['name' => 'VAT', 'percentage' => 20, 'total' => 200],
            ],
        ]);

        $this->assertCount(0, $order->lines);

        $data = OrderLine::factory()->make([
            'purchasable_type' => ProductVariant::class,
            'purchasable_id'   => ProductVariant::factory()->create()->id,
        ])->toArray();
        unset($data['currency']);

        $order->lines()->create($data);

        $this->assertCount(1, $order->refresh()->lines);
    }

    /** @test */
    public function can_update_status()
    {
        $order = Order::factory()->create([
            'user_id' => null,
            'status'  => 'status_a',
        ]);

        $this->assertEquals('status_a', $order->status);

        $order->update([
            'status' => 'status_b',
        ]);

        $this->assertEquals('status_b', $order->status);
    }

    /** @test */
    public function can_create_transaction_for_order()
    {
        Currency::factory()->create([
            'default' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'status'  => 'status_a',
        ]);

        $this->assertCount(0, $order->transactions);

        $transaction = Transaction::factory()->make()->toArray();
        unset($transaction['currency']);

        $order->transactions()->create($transaction);

        $this->assertCount(1, $order->refresh()->transactions);
    }

    /** @test */
    public function can_retrieve_different_transaction_types_for_order()
    {
        Currency::factory()->create([
            'default' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'status'  => 'status_a',
        ]);

        $this->assertCount(0, $order->transactions);

        $charge = Transaction::factory()->create([
            'order_id' => $order->id,
            'amount'   => 200,
            'refund'   => false,
        ]);

        $refund = Transaction::factory()->create([
            'order_id' => $order->id,
            'refund'   => true,
        ]);

        $order = $order->refresh();

        $this->assertCount(2, $order->transactions);

        $this->assertCount(1, $order->charges);
        $this->assertCount(1, $order->refunds);

        $this->assertEquals($charge->id, $order->charges->first()->id);
        $this->assertEquals($refund->id, $order->refunds->first()->id);
    }
}
