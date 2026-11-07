<?php

namespace Tests\Feature;

use App\Models\DailyMeal;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_todays_meal_has_extras(): void
    {
        $meal = DailyMeal::factory()->hasExtras(2)->create();

        $this->assertCount(2, $meal->extras);
    }

    public function test_settings_fall_back_to_defaults(): void
    {
        $this->assertSame('16:00', Setting::get('delivery_cutoff'));
        $this->assertSame('20:00', Setting::get('pickup_cutoff'));
    }

    public function test_settings_can_be_overridden(): void
    {
        Setting::set('ordering_open', '0');

        $this->assertSame('0', Setting::get('ordering_open'));
    }
}
