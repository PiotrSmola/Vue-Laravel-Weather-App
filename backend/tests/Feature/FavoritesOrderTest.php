<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesOpenWeather;
use Tests\TestCase;

class FavoritesOrderTest extends TestCase
{
    use RefreshDatabase, FakesOpenWeather;

    private function userWithFavorites(array $ids): User
    {
        $this->fakeOpenWeather();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach ($ids as $id) {
            $this->postJson('/api/cities', ['city_id' => $id])->assertOk();
        }

        return $user;
    }

    private function favoriteIds(): array
    {
        return array_column($this->getJson('/api/cities/user')->assertOk()->json(), 'openweather_id');
    }

    public function test_new_favorites_go_to_the_end(): void
    {
        $this->userWithFavorites([759734, 756135, 3094802]);

        $response = $this->getJson('/api/cities/user')->assertOk();

        $this->assertSame([759734, 756135, 3094802], array_column($response->json(), 'openweather_id'));
        $this->assertSame([0, 1, 2], array_column($response->json(), 'position'));
        $response->assertJsonPath('0.alias', null);
    }

    public function test_reorder_favorites(): void
    {
        $this->userWithFavorites([759734, 756135, 3094802]);

        $this->putJson('/api/cities/user/order', ['order' => [3094802, 759734, 756135]])
            ->assertOk()
            ->assertJsonPath('message', 'Zapisano kolejność ulubionych miast');

        $this->assertSame([3094802, 759734, 756135], $this->favoriteIds());

        // Kolejne dodane miasto trafia na koniec
        $this->postJson('/api/cities', ['city_id' => 765876])->assertOk();
        $this->assertSame([3094802, 759734, 756135, 765876], $this->favoriteIds());
    }

    public function test_reorder_requires_exactly_the_users_favorites(): void
    {
        $this->userWithFavorites([759734, 756135]);

        $invalid = [
            [759734],                    // brakuje miasta
            [759734, 756135, 3094802],   // miasto spoza ulubionych
            [759734, 759734],            // duplikat
            [759734, 3094802],           // podmienione miasto
        ];

        foreach ($invalid as $order) {
            $this->putJson('/api/cities/user/order', ['order' => $order])
                ->assertStatus(422)
                ->assertJsonPath('error', 'Lista musi zawierać dokładnie wszystkie ulubione miasta');
        }

        $this->putJson('/api/cities/user/order', ['order' => 'abc'])->assertStatus(422);
        $this->putJson('/api/cities/user/order', ['order' => ['x', 'y']])->assertStatus(422);
        $this->putJson('/api/cities/user/order', [])->assertStatus(422);

        $this->assertSame([759734, 756135], $this->favoriteIds());
    }

    public function test_reorder_does_not_touch_other_users(): void
    {
        $this->userWithFavorites([759734, 756135]);
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->postJson('/api/cities', ['city_id' => 756135])->assertOk();
        $this->postJson('/api/cities', ['city_id' => 759734])->assertOk();

        $this->putJson('/api/cities/user/order', ['order' => [759734, 756135]])->assertOk();

        $this->assertSame([759734, 756135], $this->favoriteIds());
        // Pierwszy użytkownik zachowuje swoją kolejność
        $this->assertSame([759734, 756135], User::orderBy('id')->first()->cities()
            ->orderBy('user_cities.position')->pluck('openweather_id')->map(fn ($id) => (int) $id)->all());

        // Drugi użytkownik ma nową kolejność
        $this->assertSame([759734, 756135], $other->cities()
            ->orderBy('user_cities.position')->pluck('openweather_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_set_and_clear_alias(): void
    {
        $this->userWithFavorites([759734]);

        $this->patchJson('/api/cities/user/759734', ['alias' => '  Dom  '])
            ->assertOk()
            ->assertJsonPath('alias', 'Dom');

        $this->getJson('/api/cities/user')
            ->assertJsonPath('0.alias', 'Dom')
            ->assertJsonPath('0.name', 'Rzeszów');

        $this->patchJson('/api/cities/user/759734', ['alias' => ''])
            ->assertOk()
            ->assertJsonPath('alias', null);

        $this->getJson('/api/cities/user')->assertJsonPath('0.alias', null);
    }

    public function test_alias_validation(): void
    {
        $this->userWithFavorites([759734]);

        $this->patchJson('/api/cities/user/759734', ['alias' => str_repeat('a', 51)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alias');

        $this->patchJson('/api/cities/user/759734', [])->assertStatus(422);
        $this->patchJson('/api/cities/user/759734', ['alias' => ['x']])->assertStatus(422);
    }

    public function test_alias_only_for_own_favorites(): void
    {
        $this->userWithFavorites([759734]);

        // Miasto istnieje w bazie, ale nie jest ulubionym tego użytkownika
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/cities/user/759734', ['alias' => 'Dom'])
            ->assertNotFound()
            ->assertJsonPath('error', 'Miasto nie jest w ulubionych');

        $this->patchJson('/api/cities/user/999', ['alias' => 'Dom'])->assertNotFound();
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->putJson('/api/cities/user/order', ['order' => []])->assertUnauthorized();
        $this->patchJson('/api/cities/user/759734', ['alias' => 'Dom'])->assertUnauthorized();
    }
}
