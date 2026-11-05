<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i', 'before:new_clock_out'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'required_with:new_break_out.*', 'after_or_equal:new_clock_in', 'before_or_equal:new_clock_out'],
            'new_break_out.*' => ['nullable', 'date_format:H:i', 'required_with:new_break_in.*', 'after:new_break_in.*', 'before_or_equal:new_clock_out'],
            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_clock_in.*' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.*' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.*' => '休憩時間が不適切な値です',
            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.*' => '休憩時間が不適切な値です',
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }

    /**
     * 入力された休憩のうち、開始・終了の両方が入っている行だけを返す（空欄の行は保存しない）。
     *
     * @return array<int, array{break_in: string, break_out: string}>
     */
    public function breaks(): array
    {
        $breakOuts = $this->input('new_break_out', []);

        return collect($this->input('new_break_in', []))
            ->map(fn (?string $breakIn, int|string $index): array => [
                'break_in' => (string) $breakIn,
                'break_out' => (string) ($breakOuts[$index] ?? ''),
            ])
            ->filter(fn (array $break): bool => $break['break_in'] !== '' && $break['break_out'] !== '')
            ->sortBy('break_in')
            ->values()
            ->all();
    }
}
