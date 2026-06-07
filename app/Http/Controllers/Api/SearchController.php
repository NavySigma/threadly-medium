<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Search semua sekaligus (global search)
    public function global(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $q = $request->q;

        $posts = Post::where('status', 'open')
            ->where(fn($query) => $query
                ->where('title', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")
            )
            ->with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color'])
            ->latest()
            ->limit(5)
            ->get();

        $users = User::where('username', 'like', "%{$q}%")
            ->select('id', 'username', 'avatar_url', 'reputation_points')
            ->limit(5)
            ->get();

        $tags = Tag::where('name', 'like', "%{$q}%")
            ->select('id', 'name', 'slug', 'color', 'usage_count')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get();

        $categories = Category::where('name', 'like', "%{$q}%")
            ->select('id', 'name', 'slug', 'description')
            ->limit(5)
            ->get();

        return response()->json([
            'query' => $q,
            'data'  => [
                'posts'      => $posts,
                'users'      => $users,
                'tags'       => $tags,
                'categories' => $categories,
            ],
        ]);
    }

    // Search post saja (dengan pagination)
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'q'           => 'required|string|min:2|max:100',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'tag_id'      => 'nullable|uuid|exists:tags,id',
        ]);

        $q = $request->q;

        $posts = Post::where('status', 'open')
            ->where(fn($query) => $query
                ->where('title', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")
            )
            ->when($request->category_id, fn($query) =>
                $query->where('category_id', $request->category_id)
            )
            ->when($request->tag_id, fn($query) =>
                $query->whereHas('tags', fn($q) =>
                    $q->where('tags.id', $request->tag_id)
                )
            )
            ->with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color'])
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    // Search user saja (dengan pagination)
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $users = User::where('username', 'like', "%{$request->q}%")
            ->select('id', 'username', 'avatar_url', 'reputation_points', 'created_at')
            ->withCount([
                'posts'     => fn($q) => $q->where('status', 'open'),
                'followers',
            ])
            ->paginate(15);

        return response()->json($users);
    }

    // Search tag saja (dengan pagination)
    public function tags(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $tags = Tag::where('name', 'like', "%{$request->q}%")
            ->select('id', 'name', 'slug', 'color', 'usage_count')
            ->orderByDesc('usage_count')
            ->paginate(20);

        return response()->json($tags);
    }
}
