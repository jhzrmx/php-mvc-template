<?php

use PHPUnit\Framework\TestCase;

class DotEnvTest extends TestCase {
    private $file;
    private $names = [];

    protected function setUp(): void {
        $this->file = tempnam(sys_get_temp_dir(), 'env');
    }

    protected function tearDown(): void {
        @unlink($this->file);
        foreach ($this->names as $name) {
            unset($_ENV[$name]);
            putenv($name);
        }
    }

    private function load($content) {
        file_put_contents($this->file, $content);
        preg_match_all('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)=/m', $content, $m);
        $this->names = array_merge($this->names, $m[1]);
        DotEnv::loadFromFile($this->file);
    }

    public function testValuesStayStrings() {
        $this->load("DT_ZERO=0123\nDT_NUM=42\nDT_BOOL=true\n");
        $this->assertSame('0123', $_ENV['DT_ZERO']);
        $this->assertSame('42', $_ENV['DT_NUM']);
        $this->assertSame('true', $_ENV['DT_BOOL']);
    }

    public function testInlineCommentsAndHash() {
        $this->load("DT_A=value # a comment\nDT_B=a#b\nDT_C=\"keep #this\"\n");
        $this->assertSame('value', $_ENV['DT_A']);
        $this->assertSame('a#b', $_ENV['DT_B']);
        $this->assertSame('keep #this', $_ENV['DT_C']);
    }

    public function testSingleQuotesAreLiteral() {
        $this->load("DT_P=hello\nDT_S='raw \${DT_P}'\n");
        $this->assertSame('raw ${DT_P}', $_ENV['DT_S']);
    }

    public function testExpansionAndEscapes() {
        $this->load("DT_BASE=hello\nDT_X=\"x-\${DT_BASE}\"\nDT_NL=\"a\\nb\"\n");
        $this->assertSame('x-hello', $_ENV['DT_X']);
        $this->assertSame("a\nb", $_ENV['DT_NL']);
    }

    public function testExportPrefixLowercaseNamesAndEmptyValues() {
        $this->load("export DT_EXP=yes\ndt_lower=ok\nDT_EMPTY=\n");
        $this->assertSame('yes', $_ENV['DT_EXP']);
        $this->assertSame('ok', $_ENV['dt_lower']);
        $this->assertSame('', $_ENV['DT_EMPTY']);
    }

    public function testUnterminatedQuoteThrowsWithoutLeakingTheValue() {
        try {
            $this->load("DT_BAD=\"super-secret\n");
            $this->fail('Expected an exception');
        } catch (Exception $e) {
            $this->assertStringContainsString('DT_BAD', $e->getMessage());
            $this->assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }

    public function testRealEnvironmentWins() {
        putenv('DT_REAL=from-server');
        $this->load("DT_REAL=from-file\n");
        $this->assertSame('from-server', env('DT_REAL'));
    }

    public function testEnvHelperCasting() {
        $_ENV['DT_T'] = 'true';
        $_ENV['DT_F'] = 'false';
        $_ENV['DT_N'] = 'null';
        $_ENV['DT_Z'] = '0123';
        $this->names = ['DT_T', 'DT_F', 'DT_N', 'DT_Z'];
        $this->assertTrue(env('DT_T'));
        $this->assertFalse(env('DT_F'));
        $this->assertNull(env('DT_N', 'ignored'));
        $this->assertSame('0123', env('DT_Z'));
        $this->assertSame('fallback', env('DT_MISSING_VAR', 'fallback'));
    }
}
