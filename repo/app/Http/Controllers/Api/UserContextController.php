<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\ReminderEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserContextController extends Controller
{
    public function myClasses(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->permissionScopes()->whereNotNull('class_id')->pluck('class_id');
        $courseIds = $user->permissionScopes()->whereNotNull('course_id')->pluck('course_id');
        $courseClassIds = ClassModel::whereIn('course_id', $courseIds)->pluck('id');
        $allClassIds = $classIds->merge($courseClassIds)->unique();

        return response()->json(
            ClassModel::whereIn('id', $allClassIds)->with('course')->get()
        );
    }

    public function reminders(Request $request): JsonResponse
    {
        return response()->json(
            ReminderEvent::where('user_id', $request->user()->id)
                ->whereNull('acknowledged_at')
                ->with('remindable')
                ->orderByDesc('scheduled_at')
                ->limit(20)
                ->get()
        );
    }

    public function acknowledgeReminder(Request $request, ReminderEvent $reminder): JsonResponse
    {
        if ($reminder->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your reminder.'], 403);
        }
        $reminder->update(['acknowledged_at' => now()]);
        return response()->json(['message' => 'Acknowledged.']);
    }
}
