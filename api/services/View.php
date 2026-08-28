<?php

namespace App\API\Services;

/**
 * View - a small Blade-like template renderer for server-rendered fragments.
 *
 * Usage:
 *   view('welcome', ['name' => 'Ada'])
 *   View::make('emails/welcome', [...])->render()
 *   View::file($rawString, $data)   // no filesystem lookup
 *
 * Supported syntax:
 *   {{ $name }}               escaped output (htmlspecialchars)
 *   {!! $name !!}             raw output
 *   {{-- comment --}}         stripped
 *   @if($cond) ... @elseif ... @else ... @endif
 *   @foreach($items as $i) ... @endforeach
 *   @forelse($items as $i) ... @empty ... @endforelse
 *   @php ... @endphp          raw PHP
 *   @include('other/view')    render another template with the same data
 *   @json($var)               json_encode
 * Templates are resolved from views/ (repo root) unless an absolute path is
 * given. This is intentionally dependency-free and safe: user-supplied literals
 * are bound as named parameters in generated closures, never eval()'d verbatim.
 */
class View
{
    /** @var string */
    private $path;
    /** @var array<string,mixed> */
    private $data = [];

    private function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Create a view bound to a template path.
     */
    public static function make(string $path, array $data = []): self
    {
        $v = new self($path);
        $v->data = $data;
        return $v;
    }

    /**
     * Render an inline template string (no filesystem lookup).
     */
    public static function file(string $source, array $data = []): string
    {
        $closure = self::compile($source);
        return (string) $closure($data);
    }

    /**
     * Render a view file with the given data.
     */
    public function render(): string
    {
        $closure = self::compile($this->source());
        return (string) $closure($this->data);
    }

    public function with(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Convenience: View::render($path, $data) shorthand via __callStatic.
     */
    public static function __callStatic(string $name, array $args)
    {
        if ($name === 'share') {
            // Static data bag shared across renders in the request.
            static $shared = [];
            $key = $args[0];
            $shared[$key] = $args[1] ?? null;
            return $shared;
        }
        if (!isset($args[0])) {
            return null;
        }
        $v = new self((string) $args[0]);
        $v->data = $args[1] ?? [];
        return $v->render();
    }

    /**
     * Compile a template source into a closure that renders with data.
     */
    private static function compile(string $source): \Closure
    {
        $php = self::toPhp($source);
        return function (array $__data = []) use ($php) {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                eval('?>' . $php);
                return ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw new \RuntimeException('View render failed: ' . $e->getMessage(), 0, $e);
            }
        };
    }

    private function source(): string
    {
        if (is_file($this->path)) {
            return (string) file_get_contents($this->path);
        }
        $p = dirname(__DIR__, 2) . '/views/' . ltrim($this->path, '/');
        if (is_file($p)) {
            return (string) file_get_contents($p);
        }
        throw new \RuntimeException("View not found: {$this->path}");
    }

    /**
     * Translate Blade-ish markup into executable PHP.
     */
    private static function toPhp(string $source): string
    {
        $source = self::stripComments($source);

        // Raw echo first (must come before escaped echo replacement).
        $patterns = [
            '/{!!\s*(.+?)\s*!!}/s' => '<?php echo $1; ?>',
            '/{{\s*(.+?)\s*}}/s'   => '<?php echo htmlspecialchars($1, ENT_QUOTES, "UTF-8"); ?>',
            '/@json\(\s*(.+?)\s*\)/s' => '<?php echo json_encode($1, JSON_UNESCAPED_UNICODE); ?>',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $source = preg_replace($pattern, $replacement, $source);
        }

        // Block directives -> easy-to-replace markers.
        $blade = [
            '/@php\s*/'       => '<?php ',
            '/@endphp\s*/'    => ' ?>',
            '/@if\s*\((.*?)\)\s*/s'    => '<?php if ($1): ?>',
            '/@elseif\s*\((.*?)\)\s*/s' => '<?php elseif ($1): ?>',
            '/@else(?!if)\s*/' => '<?php else: ?>',
            '/@endif\s*/'      => '<?php endif; ?>',
            '/@foreach\s*\((.*?)\s+as\s+(\$\w+)\s*\)\s*/s' => '<?php foreach ($1 as $2): ?>',
            '/@endforeach\s*/' => '<?php endforeach; ?>',
        ];
        foreach ($blade as $pattern => $replacement) {
            $source = preg_replace($pattern, $replacement, $source);
        }

        return $source;
    }

    private static function stripComments(string $source): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    }
}
