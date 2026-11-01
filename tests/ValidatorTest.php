<?php

use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase {
    public function testRequiredFailsWhenMissingOrBlank() {
        $v = Validator::make(['name' => '  '], ['name' => 'required', 'age' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('age', $v->errors());
    }

    public function testZeroCountsAsPresent() {
        $this->assertTrue(Validator::make(['n' => 0], ['n' => 'required|integer'])->passes());
        $this->assertTrue(Validator::make(['n' => '0'], ['n' => 'required'])->passes());
    }

    public function testOptionalEmptyFieldSkipsOtherRules() {
        $v = Validator::make(['email' => ''], ['email' => 'nullable|email']);
        $this->assertTrue($v->passes());
        $v = Validator::make([], ['email' => 'email|min:5']);
        $this->assertTrue($v->passes());
    }

    public function testEmail() {
        $this->assertTrue(Validator::make(['e' => 'a@b.co'], ['e' => 'email'])->passes());
        $this->assertTrue(Validator::make(['e' => 'nope'], ['e' => 'email'])->fails());
    }

    public function testStringLengthRules() {
        $this->assertTrue(Validator::make(['p' => 'abcdefgh'], ['p' => 'string|min:8|max:72'])->passes());
        $this->assertTrue(Validator::make(['p' => 'abc'], ['p' => 'string|min:8'])->fails());
        $this->assertTrue(Validator::make(['p' => str_repeat('a', 73)], ['p' => 'string|max:72'])->fails());
    }

    public function testMaxBytesCountsBytesNotCharacters() {
        $emoji = str_repeat("\u{1F600}", 20); // 20 characters, 80 bytes
        $this->assertTrue(Validator::make(['p' => $emoji], ['p' => 'string|max:72'])->passes());
        $this->assertTrue(Validator::make(['p' => $emoji], ['p' => 'string|max_bytes:72'])->fails());
        $this->assertTrue(Validator::make(['p' => str_repeat('a', 72)], ['p' => 'string|max_bytes:72'])->passes());
        $this->assertTrue(Validator::make(['p' => str_repeat('a', 73)], ['p' => 'string|max_bytes:72'])->fails());
    }

    public function testNumericRulesUseTheNumberNotTheLength() {
        $this->assertTrue(Validator::make(['age' => '30'], ['age' => 'integer|between:18,120'])->passes());
        $this->assertTrue(Validator::make(['age' => '17'], ['age' => 'integer|between:18,120'])->fails());
        $this->assertTrue(Validator::make(['age' => 'x'], ['age' => 'integer'])->fails());
    }

    public function testInAndNotIn() {
        $this->assertTrue(Validator::make(['r' => 'admin'], ['r' => 'in:user,admin'])->passes());
        $this->assertTrue(Validator::make(['r' => 'root'], ['r' => 'in:user,admin'])->fails());
        $this->assertTrue(Validator::make(['r' => 'root'], ['r' => 'not_in:root'])->fails());
    }

    public function testConfirmedAndSame() {
        $this->assertTrue(Validator::make(['p' => 'x', 'p_confirmation' => 'x'], ['p' => 'confirmed'])->passes());
        $this->assertTrue(Validator::make(['p' => 'x', 'p_confirmation' => 'y'], ['p' => 'confirmed'])->fails());
        $this->assertTrue(Validator::make(['a' => '1', 'b' => '1'], ['b' => 'same:a'])->passes());
    }

    public function testAlphaDashAndRegex() {
        $this->assertTrue(Validator::make(['u' => 'john_doe-1'], ['u' => 'alpha_dash'])->passes());
        $this->assertTrue(Validator::make(['u' => 'john doe'], ['u' => 'alpha_dash'])->fails());
        $this->assertTrue(Validator::make(['c' => 'AB12'], ['c' => ['regex:/^[A-Z]{2}\d{2}$/']])->passes());
    }

    public function testCustomMessages() {
        $v = Validator::make([], ['name' => 'required'], ['name.required' => 'Please tell us your name']);
        $this->assertSame('Please tell us your name', $v->firstError());
    }

    public function testOnlyTheFirstFailurePerFieldIsReported() {
        $v = Validator::make(['n' => 'x'], ['n' => 'integer|min:5']);
        $this->assertCount(1, $v->errors()['n']);
    }

    public function testValidatedKeepsOnlyFieldsWithRules() {
        $v = Validator::make(['a' => '1', 'extra' => 'x'], ['a' => 'required']);
        $this->assertSame(['a' => '1'], $v->validated());
    }

    public function testUnknownRuleThrows() {
        $this->expectException(InvalidArgumentException::class);
        Validator::make(['a' => '1'], ['a' => 'bogus'])->passes();
    }
}
