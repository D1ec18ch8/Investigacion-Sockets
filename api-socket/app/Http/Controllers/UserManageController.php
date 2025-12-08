<?php

namespace App\Http\Controllers;

use App\Events\UserFieldLocked;
use App\Events\UserFieldUnlocked;
use App\Events\UserFieldUpdated;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $users = User::orderBy('id')->paginate(15);

        return view('users.manage', [
            'users' => $users,
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'field' => ['required', 'string', 'in:name,email'],
        ]);

        $key = $this->lockKey($validated['user_id'], $validated['field']);
        $store = Cache::store('redis');
        $current = $store->get($key);

        // If already locked by another user, reject
        if ($current && $current['byId'] !== $request->user()->id) {
            return response()->json([
                'locked' => true,
                'byId' => $current['byId'],
                'byName' => $current['byName'],
            ], 409);
        }

        $payload = [
            'byId' => $request->user()->id,
            'byName' => $request->user()->name,
            'lockedAt' => Carbon::now()->toIso8601String(),
        ];

        // Store with TTL using cache (120 seconds = 2 minutes)
        $store->put($key, $payload, 120);

        broadcast(new UserFieldLocked(
            userId: $validated['user_id'],
            field: $validated['field'],
            byId: $request->user()->id,
            byName: $request->user()->name,
        ));

        return response()->json(['ok' => true]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'field' => ['required', 'string', 'in:name,email'],
        ]);

        $key = $this->lockKey($validated['user_id'], $validated['field']);
        $store = Cache::store('redis');
        $current = $store->get($key);

        // Only the locker can unlock
        if ($current && $current['byId'] !== $request->user()->id) {
            return response()->json(['ok' => false, 'lockedByOther' => true], 409);
        }

        // Atomic delete from Redis
        $store->forget($key);

        broadcast(new UserFieldUnlocked(
            userId: $validated['user_id'],
            field: $validated['field'],
            byId: $request->user()->id,
            byName: $request->user()->name,
        ));

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        broadcast(UserFieldUpdated::fromUser(
            $user,
            $request->user()->id,
            $request->user()->name,
        ));

        return back()->with('status', 'Usuario actualizado correctamente.');
    }

    private function lockKey(int $userId, string $field): string
    {
        return "locks:user:{$userId}:{$field}";
    }
}

