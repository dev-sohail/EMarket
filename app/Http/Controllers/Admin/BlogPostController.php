<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogPostController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::with('author')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(15)->withQueryString();

        return view('admin.blog.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.blog.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['author_id'] = acting_user_id();
        $data['show_on_site'] = $request->boolean('show_on_site');
        $data['is_featured'] = $request->boolean('is_featured');

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        } elseif (! empty($data['published_at'])) {
            $data['published_at'] = $this->toStoreUtc($data['published_at']);
        }

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blogs', 'public');
        }

        BlogPost::create($data);

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post created successfully.');
    }

    public function show(BlogPost $blogPost)
    {
        return view('admin.blog.show', compact('blogPost'));
    }

    public function edit(BlogPost $blogPost)
    {
        return view('admin.blog.edit', compact('blogPost'));
    }

    public function update(Request $request, BlogPost $blogPost)
    {
        $data = $this->validated($request, $blogPost->getKey());

        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['show_on_site'] = $request->boolean('show_on_site');
        $data['is_featured'] = $request->boolean('is_featured');

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        } elseif (! empty($data['published_at'])) {
            $data['published_at'] = $this->toStoreUtc($data['published_at']);
        }

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blogs', 'public');
        }

        $blogPost->update($data);

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post updated successfully.');
    }

    public function destroy(BlogPost $blogPost)
    {
        $blogPost->delete();

        return back()->with('success', 'Blog post deleted successfully.');
    }

    public function toggleShowOnSite(BlogPost $blogPost)
    {
        $blogPost->update(['show_on_site' => ! $blogPost->show_on_site]);

        return back()->with('success', 'Visibility updated.');
    }

    public function toggleFeatured(BlogPost $blogPost)
    {
        $blogPost->update(['is_featured' => ! $blogPost->is_featured]);

        return back()->with('success', 'Featured status updated.');
    }

    /**
     * `blog_posts_slug_unique` is a composite key over (slug, deleted_at) since
     * the 2026_09_28_140000 migration, so a soft-deleted post no longer holds
     * its slug. The `unique:` string form has no soft-delete option, and its
     * third parameter — the ignore id — defaults to an `id` column this schema
     * does not have, so the rule has to be the object form: ignore($id, '_id')
     * names the real key column and whereNull('deleted_at') matches the index.
     */
    protected function validated(Request $request, ?string $ignoreId): array
    {
        $slugUnique = Rule::unique('blog_posts', 'slug')->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $slugUnique->ignore($ignoreId, '_id');
        }

        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', $slugUnique],
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);
    }

    protected function toStoreUtc($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
