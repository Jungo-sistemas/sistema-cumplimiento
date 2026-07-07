<?php

namespace App\Http\Controllers;

use App\Mail\RegulationSharedMail;
use App\Models\Regulation;
use App\Models\RegulationShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegulationShareController extends Controller
{
    public function store(Request $request, Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);
        abort_unless($regulation->approval_status === 'approved', 403);

        $data = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $count = 0;

        foreach ($data['user_ids'] as $userId) {
            $recipient = User::where('id', $userId)
                ->where('group_id', $regulation->group_id)
                ->first();

            if (! $recipient || $recipient->id === $user->id) {
                continue;
            }

            $share = RegulationShare::create([
                'regulation_id' => $regulation->id,
                'sent_by'       => $user->id,
                'user_id'       => $recipient->id,
                'token'         => Str::random(64),
                'sent_at'       => now(),
            ]);

            Mail::to($recipient->email)->send(
                new RegulationSharedMail($regulation, $share, $user)
            );

            $count++;
        }

        return back()->with('success', "Notificación enviada a {$count} persona(s).");
    }

    public function track(Regulation $regulation, string $token)
    {
        $share = RegulationShare::where('regulation_id', $regulation->id)
            ->where('token', $token)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (! $share->viewed_at) {
            $share->update([
                'viewed_at' => now(),
                'viewed_ip' => request()->ip(),
            ]);
        }

        return redirect()->route('processes.show', [$regulation, 'open_pdf' => 1]);
    }
}
