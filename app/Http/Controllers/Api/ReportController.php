<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // User bikin report
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:post,comment,user',
            'target_id'   => 'required|uuid',
            'reason'      => 'required|string|in:spam,harassment,misinformation,inappropriate,other',
            'description' => 'nullable|string|max:500',
        ]);

        // Validasi target exists
        match($validated['target_type']) {
            'post'    => Post::findOrFail($validated['target_id']),
            'comment' => Comment::findOrFail($validated['target_id']),
            'user'    => User::findOrFail($validated['target_id']),
        };

        // Tidak bisa report diri sendiri
        if ($validated['target_type'] === 'user' && $validated['target_id'] === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa melaporkan diri sendiri.'], 422);
        }

        // Cek sudah pernah report target yang sama
        $alreadyReported = Report::where('reporter_id', $request->user()->id)
            ->where('target_id', $validated['target_id'])
            ->where('target_type', $validated['target_type'])
            ->whereIn('status', ['pending', 'reviewed'])
            ->exists();

        if ($alreadyReported) {
            return response()->json(['message' => 'Kamu sudah melaporkan konten ini.'], 422);
        }

        Report::create([
            'reporter_id' => $request->user()->id,
            'target_id'   => $validated['target_id'],
            'target_type' => $validated['target_type'],
            'reason'      => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status'      => 'pending',
            'created_at'  => now(),
        ]);

        return response()->json(['message' => 'Laporan berhasil dikirim.'], 201);
    }

    // Mod & Admin — list semua report
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $reports = Report::with([
                'reporter:id,username,avatar_url',
                'resolver:id,username,avatar_url',
            ])
            ->when($request->status, fn($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->target_type, fn($q) =>
                $q->where('target_type', $request->target_type)
            )
            ->latest('created_at')
            ->paginate(20);

        return response()->json($reports);
    }

    // Mod & Admin — lihat detail report
    public function show(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $report->load([
            'reporter:id,username,avatar_url',
            'resolver:id,username,avatar_url',
        ]);

        // Load target berdasarkan type
        $target = match($report->target_type) {
            'post'    => Post::with('user:id,username,avatar_url')->find($report->target_id),
            'comment' => Comment::with('user:id,username,avatar_url')->find($report->target_id),
            'user'    => User::select('id', 'username', 'avatar_url', 'reputation_points')->find($report->target_id),
        };

        return response()->json([
            'data'   => $report,
            'target' => $target,
        ]);
    }

    // Mod & Admin — update status report
    public function resolve(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($report->status === 'resolved' || $report->status === 'dismissed') {
            return response()->json(['message' => 'Report sudah diproses.'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:reviewed,resolved,dismissed',
        ]);

        $report->update([
            'status'      => $validated['status'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Report berhasil diupdate.', 'data' => $report]);
    }
}
