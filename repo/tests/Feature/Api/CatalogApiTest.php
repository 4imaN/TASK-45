<?php
namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestHelpers;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_browse_catalog(): void
    {
        $user = $this->createStudent();
        [$r1, $l1] = $this->createResourceWithLot(['name' => 'Laptop']);
        [$r2, $l2] = $this->createResourceWithLot(['name' => 'Camera']);

        $response = $this->actingAs($user)->getJson('/api/catalog');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_filter_by_type(): void
    {
        $user = $this->createStudent();
        $this->createResourceWithLot(['name' => 'Equip', 'resource_type' => 'equipment']);
        $this->createResourceWithLot(['name' => 'Venue Item', 'resource_type' => 'venue']);

        $response = $this->actingAs($user)->getJson('/api/catalog?resource_type=equipment');
        $response->assertOk();
        foreach ($response->json('data') as $item) {
            $this->assertEquals('equipment', $item['resource_type']);
        }
    }

    public function test_search_by_name(): void
    {
        $user = $this->createStudent();
        $this->createResourceWithLot(['name' => 'Oscilloscope X100']);
        $this->createResourceWithLot(['name' => 'Laptop Y200']);

        $response = $this->actingAs($user)->getJson('/api/catalog?search=Oscilloscope');
        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->contains(fn($i) => str_contains($i['name'], 'Oscilloscope')));
    }

    public function test_student_cannot_see_sensitive_items(): void
    {
        $student = $this->createStudent();
        $this->createResourceWithLot(['name' => 'Secret Gear', 'is_sensitive' => true]);
        $this->createResourceWithLot(['name' => 'Public Gear', 'is_sensitive' => false]);

        $response = $this->actingAs($student)->getJson('/api/catalog');
        $items = collect($response->json('data'));
        $this->assertFalse($items->contains(fn($i) => $i['name'] === 'Secret Gear'));
    }

    public function test_resource_detail_with_availability(): void
    {
        $user = $this->createStudent();
        [$resource, $lot] = $this->createResourceWithLot(['name' => 'Detail Item'], ['serviceable_quantity' => 5]);

        $response = $this->actingAs($user)->getJson("/api/catalog/{$resource->id}");
        $response->assertOk()->assertJsonPath('data.name', 'Detail Item');
        $this->assertArrayHasKey('availability', $response->json());
    }
}
