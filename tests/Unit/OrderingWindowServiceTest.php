<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\OrderingWindowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderingWindowServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderingWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh settings row per test; defaults: delivery 16:00, pickup 20:00.
        Setting::set('ordering_open', '1');
        Setting::set('delivery_cutoff', '16:00');
        Setting::set('pickup_cutoff', '20:00');

        $this->service = app(OrderingWindowService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public static function boundaryProvider(): array
    {
        return [
            'before delivery cutoff' => ['2026-11-08 12:00', true, true, 'open'],
            '15:59' => ['2026-11-08 15:59:59', true, true, 'open'],
            '16:00' => ['2026-11-08 16:00:00', false, true, 'delivery_closed'],
            '16:01' => ['2026-11-08 16:01:00', false, true, 'delivery_closed'],
            '19:59' => ['2026-11-08 19:59:59', false, true, 'delivery_closed'],
            '20:00' => ['2026-11-08 20:00:00', false, false, 'closed'],
            '20:01' => ['2026-11-08 20:01:00', false, false, 'closed'],
            'early morning' => ['2026-11-08 07:30', true, true, 'open'],
        ];
    }

    #[DataProvider('boundaryProvider')]
    public function test_boundaries(string $time, bool $deliveryOpen, bool $pickupOpen, string $status): void
    {
        Carbon::setTestNow(Carbon::parse($time));

        $this->assertSame($deliveryOpen, $this->service->deliveryOpen(), "deliveryOpen at {$time}");
        $this->assertSame($pickupOpen, $this->service->pickupOpen(), "pickupOpen at {$time}");
        $this->assertSame($status, $this->service->status(), "status at {$time}");
    }

    public function test_manual_override_closes_everything_even_before_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00'));
        Setting::set('ordering_open', '0');

        $this->assertFalse($this->service->deliveryOpen());
        $this->assertFalse($this->service->pickupOpen());
        $this->assertSame('closed', $this->service->status());
    }

    public function test_manual_override_blocks_order_even_in_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00'));
        Setting::set('ordering_open', '0');

        $this->expectException(ValidationException::class);

        $this->service->assertCanOrder('pickup');
    }

    public function test_owner_configurable_cutoffs_are_respected(): void
    {
        Setting::set('delivery_cutoff', '14:30');
        Setting::set('pickup_cutoff', '21:15');

        Carbon::setTestNow(Carbon::parse('2026-11-08 14:29'));
        $this->assertTrue($this->service->deliveryOpen());

        Carbon::setTestNow(Carbon::parse('2026-11-08 14:30'));
        $this->assertFalse($this->service->deliveryOpen());

        Carbon::setTestNow(Carbon::parse('2026-11-08 21:14:59'));
        $this->assertTrue($this->service->pickupOpen());

        Carbon::setTestNow(Carbon::parse('2026-11-08 21:15'));
        $this->assertFalse($this->service->pickupOpen());
    }

    public function test_cutoffs_apply_on_the_current_day(): void
    {
        // Just after midnight, before any cutoff — open.
        Carbon::setTestNow(Carbon::parse('2026-11-08 00:05'));
        $this->assertTrue($this->service->deliveryOpen());

        // 20:00 yesterday's cutoff must not carry into today.
        Carbon::setTestNow(Carbon::parse('2026-11-09 12:00'));
        $this->assertSame('open', $this->service->status());
    }

    public function test_assert_can_order_throws_after_delivery_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 16:00'));

        try {
            $this->service->assertCanOrder('delivery');
            $this->fail('Expected ValidationException for delivery after cutoff.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fulfilment_type', $e->errors());
            $this->assertStringContainsString('delivery', $e->errors()['fulfilment_type'][0]);
        }

        // Pickup is still fine at the same moment.
        try {
            $this->service->assertCanOrder('pickup');
            $this->addToAssertionCount(1); // no exception thrown — pickup still open
        } catch (ValidationException $e) {
            $this->fail('Pickup should still be open at 16:00.');
        }
    }

    public function test_assert_can_order_throws_after_pickup_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 20:00'));

        $this->expectException(ValidationException::class);

        $this->service->assertCanOrder('pickup');
    }

    public function test_assert_can_order_passes_inside_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 15:59:59'));

        // Neither call may throw inside the window.
        $this->service->assertCanOrder('delivery');
        $this->service->assertCanOrder('pickup');

        $this->addToAssertionCount(1);
    }
}
