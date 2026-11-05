<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 2：ログイン認証機能（一般ユーザー）
 */
class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);
    }

    public function test_email_is_required(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_password_is_required(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'user@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_error_is_shown_when_credentials_do_not_match(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_admin_cannot_login_from_user_login(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_user_can_login_and_is_redirected_to_attendance(): void
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/attendance');
        $this->assertAuthenticated();
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs(User::first())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
