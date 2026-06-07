<?php
// app/Services/PointService.php

namespace App\Services;

use App\Models\PointsLog;
use App\Models\User;

class PointService
{
    public function adjust(
        User $user,
        int $delta,
        string $actionType,
        ?string $referenceId = null,
        ?string $description = null
    ): void {
        if ($delta === 0) return;

        // Points tidak bisa kurang dari 1
        $newPoints    = max(1, $user->reputation_points + $delta);
        $actualDelta  = $newPoints - $user->reputation_points;

        if ($actualDelta === 0) return;

        $user->increment('reputation_points', $actualDelta);

        PointsLog::create([
            'user_id'      => $user->id,
            'points'       => $actualDelta,
            'action_type'  => $actionType,
            'reference_id' => $referenceId,
            'description'  => $description,
        ]);
    }

    public function recalculate(User $user): void
    {
        $total = PointsLog::where('user_id', $user->id)->sum('points');

        // Minimum 1
        $final = max(1, $total);

        $user->update(['reputation_points' => $final]);
    }
}
