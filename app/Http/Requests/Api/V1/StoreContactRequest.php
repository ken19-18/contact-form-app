<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|integer|in:1,2,3',
            'email' => 'required|string|email|max:255',
            'tel' => 'required|string|regex:/^[0-9]{10,11}$/',
            'address' => 'required|string|max:255',
            'building' => 'nullable|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'detail' => 'required|string|max:120',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => '姓を入力してください。',
            'last_name.required' => '名を入力してください。',

            'gender.required' => '性別を選択してください。',
            'gender.in' => '性別の値が正しくありません。',
            'gender.integer' => '性別の値が正しくありません。',

            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',

            'tel.required' => '電話番号を入力してください。',
            'tel.regex' => '電話番号はハイフンなしの10桁または11桁で入力してください。',

            'address.required' => '住所を入力してください。',

            'category_id.required' => 'カテゴリーを選択してください。',
            'category_id.exists' => '選択されたカテゴリーが存在しません。',

            'detail.required' => 'お問い合わせ内容を入力してください。',
            'detail.max' => 'お問い合わせ内容は120文字以内で入力してください。',

            'tag_ids.array' => 'タグの指定が正しくありません。',
            'tag_ids.*.integer' => 'タグの指定が正しくありません。',
            'tag_ids.*.exists' => '選択されたタグが存在しません。',
        ];
    }
}
