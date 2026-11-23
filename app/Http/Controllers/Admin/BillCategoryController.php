<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillCategoryController extends Controller
{
    /** Folder on the public disk for uploaded category icons. */
    private const ICON_DIR = 'category-icons';

    public function index(): View
    {
        $categories = BillCategory::withCount('bills')->orderBy('sort_order')->get();

        return view('content.admin.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        $data = $this->withIconImage($request, $data);

        BillCategory::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        return back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, BillCategory $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);
        $data = $this->withIconImage($request, $data, $category);

        $category->update($data);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(BillCategory $category): RedirectResponse
    {
        if ($category->bills()->exists()) {
            return back()->with('error', 'This category is in use and cannot be deleted. Deactivate it instead.');
        }

        $iconImage = $category->icon_image;
        $category->delete();

        if ($iconImage) {
            Storage::disk('public')->delete($iconImage);
        }

        return back()->with('success', 'Category deleted successfully.');
    }

    private function validateCategory(Request $request, ?BillCategory $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:60',
                Rule::unique('bill_categories', 'name')->ignore($category?->id),
            ],
            // The Tabler `icon` name and `color` are no longer edited here:
            // existing values stay as fallbacks (bills / calendar fall back to
            // the category colour) and new categories get the column defaults.
            // Raster images only: an uploaded SVG could carry script.
            'icon_image' => [
                'nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:512',
                'dimensions:min_width=24,min_height=24,max_width=1024,max_height=1024',
            ],
            'remove_icon_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_quick_pick' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Unchecked switches are simply absent from the form.
        $data['is_quick_pick'] = (bool) ($data['is_quick_pick'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /**
     * Store a newly uploaded icon (replacing the old file) or remove it, and
     * turn the request fields into the `icon_image` column value.
     */
    private function withIconImage(Request $request, array $data, ?BillCategory $category = null): array
    {
        $previous = $category?->icon_image;
        $remove = (bool) ($data['remove_icon_image'] ?? false);
        unset($data['icon_image'], $data['remove_icon_image']);

        if ($request->hasFile('icon_image')) {
            $data['icon_image'] = $request->file('icon_image')->store(self::ICON_DIR, 'public');
        } elseif ($remove) {
            $data['icon_image'] = null;
        }

        if ($previous && array_key_exists('icon_image', $data) && $data['icon_image'] !== $previous) {
            Storage::disk('public')->delete($previous);
        }

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (BillCategory::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
