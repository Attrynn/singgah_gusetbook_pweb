<?php
// Emits "name|url" for every GET route with resolvable parameters. Used by smoke.sh.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\GiftAccount;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\GuestCategory;
use App\Models\GuestGroup;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Models\Poll;
use App\Models\SeatingTable;
use App\Models\SongRequest;
use App\Models\TableAssignment;
use App\Models\User;

$event = Event::first();
$guest = Guest::first();
$token = $guest?->tokens()->where('type', 'invitation')->whereNull('revoked_at')->first()?->rawToken();

$map = [
    'event' => $event?->id,
    'guest' => $guest?->id,
    'category' => GuestCategory::first()?->id,
    'group' => GuestGroup::first()?->id,
    'poll' => Poll::first()?->id,
    'game' => Game::first()?->id,
    'table' => SeatingTable::first()?->id,
    'assignment' => TableAssignment::first()?->id,
    'song' => SongRequest::first()?->id,
    'message' => GuestbookMessage::first()?->id,
    'album' => MediaAlbum::first()?->id,
    'media' => MediaAsset::first()?->id,
    'asset' => MediaAsset::first()?->id,
    'gift' => GiftAccount::first()?->id,
    'user' => User::first()?->id,
    'owner' => User::owners()->first()?->id,
    'slug' => $event?->slug,
    'token' => $token,
    'log' => AuditLog::first()?->id,
];

$skip = ['login', 'logout', 'register', 'password.request', 'password.email', 'password.reset', 'password.store', 'verification.notice', 'verification.verify', 'verification.send', 'boost.browser-logs', 'storage.local'];

foreach (app('router')->getRoutes()->getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true)) {
        continue;
    }

    $name = $route->getName();
    $uri = $route->uri();

    if (str_starts_with($uri, '_') || $uri === 'up' || ($name && in_array($name, $skip, true))) {
        continue;
    }

    $missing = [];
    $url = $uri === '/' ? '/' : '/'.preg_replace_callback('/\{([^}?]+)(\?)?\}/', function ($m) use ($map, &$missing) {
        $value = $map[$m[1]] ?? null;

        if ($value === null) {
            $missing[] = $m[1];

            return '__MISSING__';
        }

        return (string) $value;
    }, $uri);

    if ($missing) {
        continue;
    }

    if (str_contains($uri, '/export') || str_contains($uri, '/pdf') || str_contains($uri, 'reports')) {
        $url .= (str_contains($url, '?') ? '&' : '?').'event_id='.($event?->id ?? '');
    }

    echo ($name ?? $uri).'|'.$url."\n";
}
