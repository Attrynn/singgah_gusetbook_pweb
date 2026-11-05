<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CategoryRequest;
use App\Models\Event;
use App\Models\GuestCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestCategoryController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::orderBy('event_date')->get();
        $event = $request->integer('event_id')
            ? $events->firstWhere('id', $request->integer('event_id'))
            : $events->first();

        return view('admin.categories.index', [
            'events' => $events,
            'event' => $event,
            'categories' => $event ? $event->categories()->withCount('guests')->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $slug = Str::slug($request->name);

        if ($event->categories()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Kategori ini sudah ada pada event.']);
        }

        $event->categories()->create([
            'name' => $request->name,
            'slug' => $slug,
            'color' => $request->input('color', '#8EB4D3'),
            'description' => $request->description,
        ]);

        return back()->with('status', 'Kategori ditambahkan.');
    }

    public function update(CategoryRequest $request, GuestCategory $category)
    {
        $slug = Str::slug($request->name);

        if ($category->event->categories()->where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Kategori ini sudah ada pada event.']);
        }

        $category->update([
            'name' => $request->name,
            'slug' => $slug,
            'color' => $request->input('color', '#8EB4D3'),
            'description' => $request->description,
        ]);

        return back()->with('status', 'Kategori diperbarui.');
    }

    public function destroy(GuestCategory $category)
    {
        $category->delete();

        return back()->with('status', 'Kategori dihapus. Tamu terkait tidak dihapus.');
    }
}
