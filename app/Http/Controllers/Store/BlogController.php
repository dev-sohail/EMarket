<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless((bool) setting('blogs_enabled'), 404);

        $query = BlogPost::visibleOnSite()->with('author')->latest('published_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(9)->withQueryString();
        $featured = BlogPost::visibleOnSite()->where('is_featured', true)->latest('published_at')->first();

        return view('store.blog.index', compact('posts', 'featured'));
    }

    public function show(string $slug)
    {
        abort_unless((bool) setting('blogs_enabled'), 404);

        $post = BlogPost::visibleOnSite()->where('slug', $slug)->firstOrFail();

        $post->increment('views');

        $related = BlogPost::visibleOnSite()
            ->where('_id', '!=', $post->getKey())
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('store.blog.show', compact('post', 'related'));
    }
}
