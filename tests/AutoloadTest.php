<?php

use PHPUnit\Framework\TestCase;

/**
 * Keeps libs/ PSR-4 compliant: every class file must sit at the path its namespace says
 * (JHz\Http\Route => libs/Http/Route.php) and be loadable through the autoloader.
 */
class AutoloadTest extends TestCase {
    private function libFiles() {
        $root = dirname(__DIR__) . '/libs';
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (substr($relative, -4) !== '.php' || strpos($relative, '/') === false || strpos($relative, 'RedbeanPHP/') === 0) {
                continue; // only the namespaced class files (not helpers.php, autoload.php, index.php, rb.php)
            }
            $files[$relative] = $file->getPathname();
        }
        ksort($files);
        return $files;
    }

    public function testThereAreClassFilesToCheck() {
        $this->assertNotEmpty($this->libFiles());
    }

    public function testEveryFileDeclaresTheClassItsPathSays() {
        foreach ($this->libFiles() as $relative => $path) {
            $source = file_get_contents($path);
            $expected = 'JHz\\' . str_replace('/', '\\', substr($relative, 0, -4));
            $namespace = substr($expected, 0, strrpos($expected, '\\'));
            $class = substr($expected, strrpos($expected, '\\') + 1);

            $this->assertSame(1, preg_match('/^namespace ([\w\\\\]+);/m', $source, $ns), "$relative has no namespace");
            $this->assertSame($namespace, $ns[1], "$relative is in the wrong namespace");
            $this->assertSame(1, preg_match('/^(?:abstract |final )?class ' . $class . '\b/m', $source), "$relative should declare class $class");
        }
    }

    public function testEveryClassLoadsThroughTheAutoloader() {
        foreach ($this->libFiles() as $relative => $path) {
            $fqcn = 'JHz\\' . str_replace('/', '\\', substr($relative, 0, -4));
            $this->assertTrue(class_exists($fqcn), "$fqcn could not be autoloaded");
        }
    }

    public function testClassesOutsideTheNamespaceAreIgnored() {
        $this->assertFalse(class_exists('JHz\\Nope\\Missing'));
        $this->assertFalse(class_exists('SomethingElse\\Route', false));
    }
}
