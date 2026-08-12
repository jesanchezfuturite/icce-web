<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = Post::published()->with('author')->paginate(9);

        return view('pages.blog.index', [
            'featured' => $posts->getCollection()->firstWhere('is_featured', true) ?? $posts->first(),
            'posts' => $posts,
            'topics' => Post::published()->whereNotNull('topic')->distinct()->pluck('topic'),
        ]);
    }

    public function show(Post $post): View
    {
        abort_if($post->published_at === null || $post->published_at->isFuture(), 404);

        return view('pages.blog.show', [
            'post' => $post->load('author'),
            'related' => Post::published()
                ->where('id', '!=', $post->id)
                ->where('topic', $post->topic)
                ->take(2)
                ->get(),
        ]);
    }
}
