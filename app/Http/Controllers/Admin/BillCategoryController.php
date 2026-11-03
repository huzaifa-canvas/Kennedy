<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillCategoryController extends Controller
{
    public function index(): View
    {
        $categories = BillCategory::withCount('bills')->orderBy('sort_order')->get();

        return view('content.admin.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);

        $category = BillCategory::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
        ]);


        return back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, BillCategory $category): RedirectResponse
    {
        $category->update($this->validateCategory($request, $category));


        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(BillCategory $category): RedirectResponse
    {
        if ($category->bills()->exists()) {
            return back()->with('error', 'This category is in use and cannot be deleted. Deactivate it instead.');
        }

        $category->delete();


        return back()->with('success', 'Category deleted successfully.');
    }

    private function validateCategory(Request $request, ?BillCategory $category = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:60',
                Rule::unique('bill_categories', 'name')->ignore($category?->id),
            ],
            'icon' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9\-]+$/'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_quick_pick' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
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
