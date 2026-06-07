<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color'])
            ->where('status', 'open')
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%")
                ->orWhere('body', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->reputation_points < 15) {
                return response()->json(['message' => 'Minimal 15 poin untuk membuat postingan.'], 422);
            }
        $validated = $request->validate([
            'category_id' => 'required|uuid|exists:categories,id',
            'title'       => 'required|string|min:10|max:300',
            'body'        => 'required|string|min:20',
            'tags'        => 'nullable|array|max:5',
            'tags.*'      => 'uuid|exists:tags,id',
        ]);

        $post = Post::create([
            'user_id'     => $request->user()->id,
            'category_id' => $validated['category_id'],
            'title'       => $validated['title'],
            'body'        => $validated['body'],
        ]);

        // Attach tags jika ada
        if (!empty($validated['tags'])) {
            $post->tags()->attach($validated['tags']);

            // Update usage_count di tabel tags
            Tag::whereIn('id', $validated['tags'])->increment('usage_count');
        }

        $post->load(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color']);

        return response()->json(['message' => 'Post berhasil dibuat.', 'data' => $post], 201);
    }

    public function show(Post $post): JsonResponse
    {
        // Increment view count
        $post->increment('view_count');

        $post->load([
            'user:id,username,avatar_url,reputation_points',
            'category:id,name,slug',
            'tags:id,name,slug,color',
            'acceptedAnswer.user:id,username,avatar_url',
        ]);

        return response()->json(['data' => $post]);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        // Hanya owner yang bisa edit, admin/mod tidak bisa
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($post->status === 'closed') {
            return response()->json(['message' => 'Post sudah ditutup, tidak bisa diedit.'], 422);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|uuid|exists:categories,id',
            'title'       => 'sometimes|string|min:10|max:300',
            'body'        => 'sometimes|string|min:20',
            'tags'        => 'nullable|array|max:5',
            'tags.*'      => 'uuid|exists:tags,id',
        ]);

        $post->update(collect($validated)->except('tags')->toArray());

        if ($request->has('tags')) {
            $oldTagIds = $post->tags()->pluck('tags.id')->toArray();
            if ($oldTagIds) Tag::whereIn('id', $oldTagIds)->decrement('usage_count');

            $newTags = $validated['tags'] ?? [];
            $post->tags()->sync($newTags);
            if ($newTags) Tag::whereIn('id', $newTags)->increment('usage_count');
        }

        $post->load(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color']);

        return response()->json(['message' => 'Post berhasil diupdate.', 'data' => $post]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        // Owner ATAU mod ATAU admin bisa hapus
        if ($user->id !== $post->user_id && !$user->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $post->update(['status' => 'deleted']);

        return response()->json(['message' => 'Post berhasil dihapus.']);
    }
}
