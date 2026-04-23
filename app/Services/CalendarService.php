<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

final class CalendarService
{
    /**
     * @return array<int, array{id: string, title: string, start_formatted: string, end_formatted: string, hours: float}>
     */
    public function getTodayEvents(User $user): array
    {
        $token = $this->getValidToken($user);
        if ($token === null) {
            return [];
        }

        $date = Carbon::today();

        $response = Http::withToken($token)
            ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'timeMin' => $date->copy()->startOfDay()->toIso8601String(),
                'timeMax' => $date->copy()->endOfDay()->toIso8601String(),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 50,
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('items', []))
            ->filter(fn (array $item) => isset($item['start']['dateTime'], $item['end']['dateTime']))
            ->map(function (array $item): array {
                $start = Carbon::parse($item['start']['dateTime']);
                $end = Carbon::parse($item['end']['dateTime']);
                $hours = round($start->diffInMinutes($end) / 60, 2);

                return [
                    'id' => $item['id'],
                    'title' => $item['summary'] ?? 'Untitled event',
                    'start_formatted' => $start->format('H:i'),
                    'end_formatted' => $end->format('H:i'),
                    'hours' => $hours,
                ];
            })
            ->values()
            ->toArray();
    }

    public function hasToken(User $user): bool
    {
        return $user->google_access_token !== null;
    }

    private function getValidToken(User $user): ?string
    {
        if ($user->google_access_token === null) {
            return null;
        }

        if ($user->google_token_expires_at !== null && $user->google_token_expires_at->isPast()) {
            return $this->refreshToken($user);
        }

        return $user->google_access_token;
    }

    private function refreshToken(User $user): ?string
    {
        if ($user->google_refresh_token === null) {
            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $newToken = $data['access_token'] ?? null;
        if ($newToken === null) {
            return null;
        }

        $user->update([
            'google_access_token' => $newToken,
            'google_token_expires_at' => now()->addSeconds(max(0, ($data['expires_in'] ?? 3600) - 60)),
        ]);

        return $newToken;
    }
}
