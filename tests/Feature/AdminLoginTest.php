<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 3：ログイン認証機能（管理者）
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
    }

    public function test_email_is_required(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_password_is_required(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_error_is_shown_when_credentials_do_not_match(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_general_user_cannot_login_from_admin_login(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    public function test_admin_can_login_and_is_redirected_to_admin_attendance_list(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticated();
    }

    public function test_admin_can_logout(): void
    {
        $this->actingAs(User::first())->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertGuest();
    }
}
