<?php

use PHPUnit\Framework\TestCase;

require_once BASE_PATH . '/models/User.php';

/**
 * Integration tests against an in-memory SQLite database (needs the pdo_sqlite extension).
 */
class UserModelTest extends TestCase {
    protected function setUp(): void {
        R::setup('sqlite::memory:');
        R::freeze(false);
        R::nuke();
    }

    protected function tearDown(): void {
        R::close();
    }

    public function testCreateHashesThePasswordAndDefaultsToUserRole() {
        $bean = User::create('alice', 'password123', 'alice@example.com');
        $this->assertNotSame('password123', $bean->password_hash);
        $this->assertTrue(User::verifyPassword($bean, 'password123'));
        $this->assertFalse(User::verifyPassword($bean, 'wrong-password'));
        $this->assertSame('user', $bean->role);
        $this->assertSame(0, (int) $bean->token_version);
    }

    public function testToArrayHidesSecrets() {
        $bean = User::create('alice', 'password123');
        $array = User::toArray($bean);
        $this->assertArrayNotHasKey('password_hash', $array);
        $this->assertArrayNotHasKey('token_version', $array);
        $this->assertSame('alice', $array['username']);
        $this->assertSame('user', $array['role']);
    }

    public function testDuplicateUsernameIsRejected() {
        User::create('alice', 'password123');
        $this->expectException(InvalidArgumentException::class);
        User::create('alice', 'password456');
    }

    public function testPasswordAndEmailAreValidated() {
        try {
            User::create('bob', 'short');
            $this->fail('Expected a password error');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Password', $e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        User::create('bob', 'password123', 'not-an-email');
    }

    public function testInvalidRoleIsRejected() {
        $this->expectException(InvalidArgumentException::class);
        User::create('mallory', 'password123', null, 'superuser');
    }

    public function testRevokeTokensBumpsTheVersion() {
        $bean = User::create('alice', 'password123');
        User::revokeTokens($bean);
        $this->assertSame(1, (int) User::findBean($bean->id)->token_version);
    }

    public function testChangingThePasswordRevokesTokens() {
        $bean = User::create('alice', 'password123');
        $updated = User::updateById($bean->id, ['password' => 'new-password-456']);
        $this->assertTrue(User::verifyPassword($updated, 'new-password-456'));
        $this->assertSame(1, (int) $updated->token_version);
    }

    public function testUpdatingToAnExistingUsernameIsRejected() {
        User::create('alice', 'password123');
        $bob = User::create('bob', 'password123');
        $this->expectException(InvalidArgumentException::class);
        User::updateById($bob->id, ['username' => 'alice']);
    }

    public function testUpdateCannotChangeTheRole() {
        $bean = User::create('alice', 'password123');
        $updated = User::updateById($bean->id, ['role' => 'admin']);
        $this->assertSame('user', $updated->role);
    }

    public function testSetRole() {
        $bean = User::create('alice', 'password123');
        User::setRole($bean, 'admin');
        $this->assertTrue(User::isAdmin(User::findBean($bean->id)));
    }

    public function testDeleteById() {
        $bean = User::create('alice', 'password123');
        $this->assertTrue(User::deleteById($bean->id));
        $this->assertNull(User::find($bean->id));
        $this->assertFalse(User::deleteById($bean->id));
    }

    public function testAllReturnsAList() {
        User::create('a1', 'password123');
        User::create('a2', 'password123');
        $all = User::all();
        $this->assertSame([0, 1], array_keys($all));
    }

    public function testPaginate() {
        for ($i = 1; $i <= 25; $i++) {
            User::create('user' . $i, 'password123');
        }
        $page = User::paginate(2, 10);
        $this->assertCount(10, $page['data']);
        $this->assertSame('user11', $page['data'][0]['username']);
        $this->assertSame(25, $page['meta']['total']);
        $this->assertSame(3, $page['meta']['last_page']);

        $last = User::paginate(3, 10);
        $this->assertCount(5, $last['data']);
    }

    public function testPaginateClampsBadInput() {
        User::create('only', 'password123');
        $page = User::paginate(-4, 100000);
        $this->assertSame(1, $page['meta']['page']);
        $this->assertSame(100, $page['meta']['per_page']);
    }

    public function testWhereAndCount() {
        User::create('a', 'password123');
        $admin = User::create('b', 'password123');
        User::setRole($admin, 'admin');
        $this->assertSame(1, User::count('role = ?', ['admin']));
        $rows = User::where('role = ?', ['admin']);
        $this->assertCount(1, $rows);
        $this->assertSame('b', $rows[0]['username']);
    }

    public function testOrderByIsValidated() {
        $this->expectException(InvalidArgumentException::class);
        User::where('1 = 1', [], 'id; DROP TABLE user');
    }
}
