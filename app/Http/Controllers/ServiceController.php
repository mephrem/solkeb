<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Setup → Services: the old price list of extras (bills now use free-text charges with price × quantity).
 */
class ServiceController extends Controller
{
    public const CATEGORIES = ['restaurant' => 'Restaurant', 'bar' => 'Bar', 'laundry' => 'Laundry', 'transport' => 'Transport', 'hall' => 'Meeting hall', 'other' => 'Other'];

    public function index()
    {
        return view('setup.services', [
            'services' => Service::withCount('charges')->orderByDesc('is_active')->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('setup.service-form', ['service' => new Service(['category' => 'restaurant', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Service added.');
    }

    public function edit(Service $service)
    {
        return view('setup.service-form', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));

        return redirect()->route('services.index')->with('success', "{$service->name} updated.");
    }

    public function destroy(Service $service)
    {
        if ($service->charges()->exists()) {
            return back()->with('error', "{$service->name} is on past bills, so it can't be deleted. Untick Active to hide it instead.");
        }

        $service->delete();

        return redirect()->route('services.index')->with('success', "{$service->name} deleted.");
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('services', 'name')->ignore($service?->id)],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
