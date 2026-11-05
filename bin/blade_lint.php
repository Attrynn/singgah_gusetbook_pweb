<?php
// Real Blade compile + PHP lint for every view. Run: php bin/blade_lint.php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;

Artisan::call('view:clear');

$root = __DIR__.'/../resources/views';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];

foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
        $files[] = $f->getPathname();
    }
}

sort($files);

$compiler = app(Illuminate\View\Compilers\BladeCompiler::class);
$broken = [];
$ok = 0;

foreach ($files as $f) {
    $rel = substr($f, strlen($root) + 1);

    try {
        Blade::compile($f);
    } catch (Throwable $e) {
        $broken[] = "COMPILE  $rel  ::  ".substr($e->getMessage(), 0, 240);

        continue;
    }

    $compiled = $compiler->getCompiledPath($f);

    if (! is_file($compiled)) {
        $broken[] = "NOFILE   $rel  ::  compiled file missing";

        continue;
    }

    exec('php -l '.escapeshellarg($compiled).' 2>&1', $out, $rc);
    $err = collect($out)->first(fn ($l) => str_contains($l, 'Parse error') || str_contains($l, 'Fatal error'));
    $out = [];

    if ($rc !== 0 || $err !== null) {
        $broken[] = "SYNTAX   $rel  ::  ".trim((string) $err);
    } else {
        $ok++;
    }
}

if ($broken) {
    echo implode("\n", $broken)."\n\n".count($broken)." BROKEN, $ok OK OF ".count($files)."\n";
    exit(1);
}

echo "ALL ".count($files)." VIEWS CLEAN\n";
