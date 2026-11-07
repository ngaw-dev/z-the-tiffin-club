<?php

namespace Tests\Feature\PublicApi;

use App\Models\DailyMeal;
use App\Models\Extra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_todays_meal_with_extras_and_ordering_placeholder(): void
    {
        $meal = DailyMeal::factory()->create();
        Extra::factory()->count(2)->create(['daily_meal_id' => $meal->id]);

        $response = $this->getJson('/api/v1/menu/today');

        $response->assertOk()
            ->assertJsonPath('data.meal.name', $meal->name)
            ->assertJsonPath('data.meal.price', $meal->price)
            ->assertJsonPath('data.meal.max_per_order', 5)
            ->assertJsonCount(2, 'data.meal.extras')
            ->assertJsonStructure([
                'data' => [
                    'meal' => ['date', 'name', 'description', 'price', 'photo_url', 'max_per_order', 'extras'],
                    'ordering' => [
                        'delivery_open', 'pickup_open', 'delivery_cutoff', 'pickup_cutoff',
                        'pickup_window' => ['start', 'end'],
                        'restaurant_address', 'delivery_city',
                    ],
                ],
            ]);
    }

    public function test_returns_404_when_no_meal_published_today(): void
    {
        DailyMeal::factory()->create(['menu_date' => today()->subDay()]);

        $this->getJson('/api/v1/menu/today')->assertNotFound();
    }

    public function test_ordering_placeholder_exposes_cutoffs_from_settings(): void
    {
        DailyMeal::factory()->create();

        $this->getJson('/api/v1/menu/today')
            ->assertOk()
            ->assertJsonPath('data.ordering.delivery_cutoff', '16:00')
            ->assertJsonPath('data.ordering.pickup_cutoff', '20:00')
            ->assertJsonPath('data.ordering.pickup_window.start', '18:00');
    }
}
