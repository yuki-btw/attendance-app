<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 1：認証機能（一般ユーザー）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト 太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_name_is_required(): void
    {
        $response = $this->from('/register')->post('/register', $this->validInput(['name' => '']));

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    public function test_email_is_required(): void
    {
        $response = $this->from('/register')->post('/register', $this->validInput(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_password_must_be_at_least_8_characters(): void
    {
        $response = $this->from('/register')->post('/register', $this->validInput([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    public function test_password_must_match_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', $this->validInput([
            'password_confirmation' => 'different123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません']);
    }

    public function test_password_is_required(): void
    {
        $response = $this->from('/register')->post('/register', $this->validInput(['password' => '']));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_user_is_saved_when_input_is_valid(): void
    {
        $response = $this->post('/register', $this->validInput());

        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'name' => 'テスト 太郎',
            'email' => 'test@example.com',
            'admin_status' => false,
        ]);
        $this->assertAuthenticated();
    }
}
