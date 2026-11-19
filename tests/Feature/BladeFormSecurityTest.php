<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards against the regression that left 22 AJAX-less Blade forms submitting
 * without a CSRF token (and several destroy forms without a method spoof).
 * Both defects only surfaced in a real browser: Laravel returns 419/405 while
 * feature tests that call the route directly still pass.
 */
class BladeFormSecurityTest extends TestCase
{
    public function test_every_post_form_carries_a_csrf_token(): void
    {
        $violations = [];

        foreach ($this->postForms() as $form) {
            if (! preg_match('/@csrf|csrf_field\(|name=["\']_token/i', $form['body'])) {
                $violations[] = $form['file'].':'.$form['line'].' ['.$form['route'].'] missing @csrf';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_forms_submitting_to_non_post_routes_spoof_the_method(): void
    {
        $violations = [];

        foreach ($this->postForms() as $form) {
            $methods = $form['route'] && Route::has($form['route'])
                ? Route::getRoutes()->getByName($form['route'])->methods()
                : ['POST'];

            $realVerbs = array_values(array_diff($methods, ['POST', 'HEAD', 'GET']));

            if (! $realVerbs) {
                continue;
            }

            $verb = $realVerbs[0];

            if (! preg_match('/@method\(\s*["\']'.$verb.'["\']\s*\)/i', $form['body'])) {
                $violations[] = $form['file'].':'.$form['line'].' ['.$form['route'].'] missing @method(\''.$verb.'\')';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * @return array<int, array{file: string, line: int, route: ?string, body: string}>
     */
    private function postForms(): array
    {
        $root = resource_path('views');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $forms = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $content = file_get_contents($path);

            if (! preg_match_all('/<form\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$tag, $offset]) {
                if (! preg_match('/method\s*=\s*["\']?post/i', $tag)) {
                    continue;
                }

                $end = strpos($content, '</form>', $offset);
                $body = $end === false ? substr($content, $offset, 2500) : substr($content, $offset, $end - $offset);

                $route = null;
                if (preg_match('/route\(\s*["\']([a-zA-Z0-9_.\-]+)["\']/', $tag, $routeMatch)) {
                    $route = $routeMatch[1];
                }

                $forms[] = [
                    'file' => str_replace(resource_path('views').'/', '', $path),
                    'line' => substr_count(substr($content, 0, $offset), "\n") + 1,
                    'route' => $route,
                    'body' => $body,
                ];
            }
        }

        return $forms;
    }
}
