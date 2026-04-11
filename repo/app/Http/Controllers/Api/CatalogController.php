<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Domain\Availability\AvailabilityService;
use App\Http\Resources\ResourceResource;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(protected AvailabilityService $availability) {}

    public function index(Request $request)
    {
        $query = Resource::with(['department', 'inventoryLots', 'venues'])
            ->where('status', '!=', 'delisted');

        // Students cannot see sensitive items
        if ($request->user()->isStudent()) {
            $query->where('is_sensitive', false);
        }

        if ($request->filled('resource_type')) $query->where('resource_type', $request->resource_type);
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $resources = $query->paginate($request->per_page ?? 20);

        return ResourceResource::collection($resources)->additional([
            'meta' => ['availability_computed' => true],
        ]);
    }

    public function show(Request $request, Resource $resource)
    {
        // Students cannot view sensitive or delisted resources directly
        if ($request->user()->isStudent()) {
            if ($resource->is_sensitive || $resource->status === 'delisted') {
                abort(403, 'You do not have access to this resource.');
            }
        }

        $resource->load(['department', 'inventoryLots', 'venues']);
        $available = $this->availability->getAvailableQuantity($resource);

        $additional = [
            'availability' => [
                'available_quantity' => $available,
                'lots' => $resource->inventoryLots->map(fn($lot) => [
                    'id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'location' => $lot->location,
                    'condition' => $lot->condition,
                    'available' => $this->availability->getLotAvailableQuantity($lot),
                    'total' => $lot->serviceable_quantity,
                ]),
            ],
        ];

        // For venue resources, expose available time slots without reservation details
        if ($resource->resource_type === 'venue' && $resource->venues->isNotEmpty()) {
            $venue = $resource->venues->first();
            $slots = \App\Models\VenueTimeSlot::where('venue_id', $venue->id)
                ->where('is_available', true)
                ->whereNull('reserved_by_reservation_id')
                ->where('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(50)
                ->get(['id', 'date', 'start_time', 'end_time']);

            $additional['venue'] = [
                'id' => $venue->id,
                'capacity' => $venue->capacity,
                'location' => $venue->location,
                'amenities' => $venue->amenities,
                'available_slots' => $slots,
            ];
        }

        return (new ResourceResource($resource))->additional($additional);
    }
}
