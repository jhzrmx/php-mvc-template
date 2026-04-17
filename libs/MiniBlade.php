<?php

/**
 * @package MiniBlade
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 */
class MiniBlade {
    protected string $views;
    protected string $cache;
    protected array $directives = [];
    protected array $sections = [];
    protected array $sectionStack = [];

    public function __construct(string $views, string $cache) {
        $this->views = rtrim($views, '/');
        $this->cache = rtrim($cache, '/');
        if (!is_dir($this->cache)) mkdir($this->cache, 0777, true);
    }

    public function directive(string $name, callable $handler): void {
        $this->directives[$name] = $handler;
    }

    public function render(string $view, array $data = []): string {
        $compiled = $this->compileView($view);
        extract($data, EXTR_SKIP);
        ob_start();
        include $compiled;
        return ob_get_clean();
    }

    protected function compileView(string $view): string {
        $source = $this->resolveView($view);
        $target = $this->cache.'/'.md5($source).'.php';
        if (!file_exists($target) || filemtime($source) > filemtime($target)) {
            $code = file_get_contents($source);
            $code = $this->compile($code);
            file_put_contents($target, $code);
        }
        return $target;
    }

    protected function resolveView(string $view): string {
        $path = $this->views.'/'.str_replace('.', '/', $view).'.blade.php';
        $real = realpath($path);
        $base = realpath($this->views);
        if (!$real || !str_starts_with($real, $base)) throw new Exception('Invalid view path');
        return $real;
    }

    protected function compile(string $code): string {
        $code = $this->compileExtends($code);
        $code = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?= htmlspecialchars($1, ENT_QUOTES, "UTF-8") ?>', $code);
        $code = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $code);
        $map = [
            '/@if\((.*?)\)/' => '<?php if($1): ?>',
            '/@elseif\((.*?)\)/' => '<?php elseif($1): ?>',
            '/@else/' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',
            '/@foreach\((.*?)\)/' => '<?php foreach($1): ?>',
            '/@endforeach/' => '<?php endforeach; ?>',
            '/@yield\((.*?)\)/' => '<?= $this->yieldSection($1) ?>',
            '/@section\((.*?)\)/' => '<?php $this->startSection($1); ?>',
            '/@endsection/' => '<?php $this->endSection(); ?>',
            '/@csrf/' => '<input type="hidden" name="_token" value="<?= bin2hex(random_bytes(16)) ?>">',
        ];
        $code = preg_replace(array_keys($map), array_values($map), $code);
        $code = preg_replace_callback('/@include\((.*?)\)/', fn($m) => '<?= $this->renderInclude('.$m[1].') ?>', $code);
        foreach ($this->directives as $name => $handler) {
            $code = preg_replace_callback('/@'.$name.'\((.*?)\)/', fn($m) => $handler($m[1]), $code);
        }
        return $code;
    }

    protected function compileExtends(string $code): string {
        if (preg_match('/@extends\((.*?)\)/', $code, $m)) {
            $parent = trim($m[1], "'\"");
            $code = preg_replace('/@extends\((.*?)\)/', '', $code, 1);
            $child = $code;
            $layout = file_get_contents($this->resolveView($parent));
            return $child."\n".$layout;
        }
        return $code;
    }

    public function startSection(string $name): void {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void {
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    public function yieldSection(string $name): string {
        return $this->sections[$name] ?? '';
    }

    public function renderInclude(string $view, array $data = []): string {
        return $this->render($view, $data);
    }
}