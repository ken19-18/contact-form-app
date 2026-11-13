<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_page_displays_categories_and_tags(): void
    {
        Category::create(['content' => '配送について']);
        Tag::create(['name' => 'ご要望']);

        $this->get('/')->assertOk()->assertViewHas('categories')->assertViewHas('tags')
            ->assertSee('配送について')->assertSee('ご要望');
        $this->get('/thanks')->assertOk()->assertViewIs('contact.thanks');
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
    }

    public function test_contact_confirmation_shows_validated_input_and_rejects_invalid_input(): void
    {
        $category = Category::create(['content' => '配送について']);
        $payload = $this->validContact($category);

        $this->post('/contacts/confirm', $payload)->assertOk()->assertViewIs('contact.confirm')->assertSee('佐藤')->assertSee('hanako@example.com');
        $this->from('/')->post('/contacts/confirm', array_merge($payload, ['tel' => 'bad']))
            ->assertRedirect('/')->assertSessionHasErrors('tel');
    }

    public function test_contact_submission_stores_contact_and_tag_links_then_rejects_invalid_phone(): void
    {
        $category = Category::create(['content' => '配送について']);
        $tag = Tag::create(['name' => 'ご要望']);
        $payload = array_merge($this->validContact($category), ['tag_ids' => [$tag->id]]);

        $this->post('/contacts', $payload)->assertRedirect('/thanks');
        $contact = Contact::where('email', $payload['email'])->firstOrFail();
        $this->assertDatabaseHas('contact_tag', ['contact_id' => $contact->id, 'tag_id' => $tag->id]);
        $this->from('/')->post('/contacts', array_merge($payload, ['tel' => '03-1234']))
            ->assertRedirect('/')->assertSessionHasErrors('tel');
    }

    public function test_admin_search_filters_contacts_and_paginates_seven_per_page(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::create(['content' => '配送について']);
        for ($i = 0; $i < 8; $i++) {
            Contact::create($this->validContact($category, ['email' => "person{$i}@example.com", 'gender' => $i === 0 ? 2 : 1]));
        }
        $otherCategory = Category::create(['content' => '商品について']);

        Contact::create($this->validContact($otherCategory, [
            'email' => 'other@example.com',
        ]));
        $dateContact = Contact::create($this->validContact($category, [
            'email' => 'date@example.com',
        ]));

        $dateContact->forceFill([
            'created_at' => now()->subDays(3),
        ])->save();

        $this->get('/admin?gender=2&keyword=person0')->assertOk()->assertViewHas('contacts', fn ($paginator) => $paginator->total() === 1);
        $this->get('/admin?category_id='.$otherCategory->id)
            ->assertOk()
            ->assertViewHas('contacts', fn ($paginator) => $paginator->total() === 1);

        $date = now()->subDays(3)->toDateString();

        $this->get('/admin?date='.$date)
            ->assertOk()
            ->assertViewHas('contacts', fn ($paginator) => $paginator->total() === 1);
        $this->get('/admin')->assertOk()->assertViewHas('contacts', fn ($paginator) => $paginator->perPage() === 7 && $paginator->total() === 10);
    }

    public function test_admin_contact_detail_includes_category_and_tags(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::create(['content' => '配送について']);
        $contact = Contact::create($this->validContact($category));
        $tag = Tag::create(['name' => 'ご要望']);
        $contact->tags()->attach($tag);

        $this->get("/admin/contacts/{$contact->id}")->assertOk()->assertViewIs('admin.show')
            ->assertSee('配送について')->assertSee('ご要望');
    }

    public function test_admin_contact_delete_removes_record_and_tag_pivot(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::create(['content' => '配送について']);
        $contact = Contact::create($this->validContact($category));
        $tag = Tag::create(['name' => 'ご要望']);
        $contact->tags()->attach($tag);

        $this->delete("/admin/contacts/{$contact->id}")->assertRedirect('/admin');
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('contact_tag', ['contact_id' => $contact->id, 'tag_id' => $tag->id]);
    }

    public function test_tag_crud_routes_require_auth_and_create_update_delete(): void
    {
        $this->get('/admin/tags/1/edit')->assertRedirect('/login');
        $this->post('/admin/tags', ['name' => '未認証タグ'])
            ->assertRedirect('/login');

        $this->put('/admin/tags/1', ['name' => '更新タグ'])
            ->assertRedirect('/login');

        $this->delete('/admin/tags/1')
            ->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/admin/tags', ['name' => '新しいタグ'])->assertRedirect('/admin');
        $this->from('/admin')->post('/admin/tags', ['name' => ''])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('name');

        $this->from('/admin')->post('/admin/tags', ['name' => '新しいタグ'])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('name');
        $tag = Tag::where('name', '新しいタグ')->firstOrFail();
        $this->get("/admin/tags/{$tag->id}/edit")->assertOk()->assertSee('新しいタグ');
        $this->put("/admin/tags/{$tag->id}", ['name' => '更新後のタグ'])->assertRedirect('/admin');
        Tag::create(['name' => '既存タグ']);

        $this->from("/admin/tags/{$tag->id}/edit")
            ->put("/admin/tags/{$tag->id}", ['name' => '既存タグ'])
            ->assertRedirect("/admin/tags/{$tag->id}/edit")
            ->assertSessionHasErrors('name');
        $this->from("/admin/tags/{$tag->id}/edit")
            ->put("/admin/tags/{$tag->id}", ['name' => ''])
            ->assertRedirect("/admin/tags/{$tag->id}/edit")
            ->assertSessionHasErrors('name');
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => '更新後のタグ']);
        $this->delete("/admin/tags/{$tag->id}")->assertRedirect('/admin');
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_api_contact_list_filters_paginates_and_returns_422_for_invalid_filter(): void
    {
        $category = Category::create(['content' => '配送について']);
        for ($i = 0; $i < 3; $i++) {
            Contact::create($this->validContact($category, ['email' => "api{$i}@example.com"]));
        }
        $otherCategory = Category::create(['content' => '商品について']);

        Contact::create($this->validContact($otherCategory, [
            'email' => 'api-other@example.com',
        ]));

        $dateContact = Contact::create($this->validContact($category, [
            'email' => 'api-date@example.com',
        ]));

        $dateContact->forceFill([
            'created_at' => now()->subDays(3),
        ])->save();

        $this->getJson('/api/v1/contacts?keyword=api0&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.email', 'api0@example.com');
        $this->getJson('/api/v1/contacts?gender=8')->assertUnprocessable();
        $this->getJson('/api/v1/contacts?category_id='.$otherCategory->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'api-other@example.com');

        $date = now()->subDays(3)->toDateString();

        $this->getJson('/api/v1/contacts?date='.$date)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'api-date@example.com');
    }

    public function test_api_contact_detail_returns_resource_and_404_for_missing_contact(): void
    {
        $category = Category::create(['content' => '配送について']);
        $contact = Contact::create($this->validContact($category));
        $this->getJson("/api/v1/contacts/{$contact->id}")->assertOk()
            ->assertJsonPath('data.email', $contact->email)->assertJsonPath('data.category.content', '配送について');
        $this->getJson('/api/v1/contacts/99999')->assertNotFound();
    }

    public function test_api_contact_create_returns_201_and_422_for_invalid_payload(): void
    {
        $category = Category::create(['content' => '配送について']);
        $payload = $this->validContact($category);
        $tag = Tag::create(['name' => 'API登録タグ']);

        $payload['tag_ids'] = [$tag->id];
        $this->postJson('/api/v1/contacts', $payload)->assertCreated()->assertJsonPath('data.email', $payload['email']);
        $this->assertDatabaseHas('contacts', ['email' => $payload['email']]);
        $contact = Contact::where('email', $payload['email'])->firstOrFail();

        $this->assertDatabaseHas('contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $tag->id,
        ]);
        $this->postJson('/api/v1/contacts', array_merge($payload, ['tel' => 'bad']))->assertUnprocessable();
    }

    public function test_api_contact_update_returns_200_and_404_or_422_for_errors(): void
    {
        $category = Category::create(['content' => '配送について']);
        $contact = Contact::create($this->validContact($category));
        $payload = $this->validContact($category, ['email' => 'updated@example.com']);
        $this->putJson("/api/v1/contacts/{$contact->id}", $payload)->assertOk()->assertJsonPath('data.email', 'updated@example.com');
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'email' => 'updated@example.com']);
        $this->putJson('/api/v1/contacts/99999', $payload)->assertNotFound();
        $this->putJson("/api/v1/contacts/{$contact->id}", array_merge($payload, ['tel' => 'bad']))->assertUnprocessable();
    }

    public function test_api_contact_delete_returns_204_and_404_when_missing(): void
    {
        $category = Category::create(['content' => '配送について']);
        $contact = Contact::create($this->validContact($category));
        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertNoContent();
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertNotFound();
    }

    public function test_admin_can_download_filtered_csv_in_newest_order(): void
    {
        $this->actingAs(User::factory()->create());

        $targetCategory = Category::create(['content' => '配送について']);
        $otherCategory = Category::create(['content' => '商品について']);

        $olderContact = Contact::create($this->validContact($targetCategory, [
            'email' => 'older@example.com',
        ]));

        $newerContact = Contact::create($this->validContact($targetCategory, [
            'email' => 'newer@example.com',
        ]));

        $otherContact = Contact::create($this->validContact($otherCategory, [
            'email' => 'other@example.com',
        ]));

        $olderContact->forceFill([
            'created_at' => now()->subDays(2),
        ])->save();

        $newerContact->forceFill([
            'created_at' => now(),
        ])->save();

        $otherContact->forceFill([
            'created_at' => now()->subDays(1),
        ])->save();

        // カテゴリで絞り込んだCSVを確認
        $response = $this->get('/contacts/export?category_id='.$targetCategory->id);

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('配送について', $csv);
        $this->assertStringContainsString('older@example.com', $csv);
        $this->assertStringContainsString('newer@example.com', $csv);
        $this->assertStringNotContainsString('other@example.com', $csv);
        $filteredDate = now()->subDays(2)->toDateString();

        $response = $this->get('/contacts/export?'.http_build_query([
            'keyword' => 'older@example.com',
            'gender' => 2,
            'date' => $filteredDate,
        ]));

        $response->assertOk();

        $filteredCsv = $response->streamedContent();

        $this->assertStringContainsString('older@example.com', $filteredCsv);
        $this->assertStringNotContainsString('newer@example.com', $filteredCsv);
        $this->assertStringNotContainsString('other@example.com', $filteredCsv);

        $response = $this->get('/contacts/export');

        $csv = $response->streamedContent();
        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));

        $this->assertStringContainsString('ID', $csv);
        $this->assertStringContainsString('氏名', $csv);
        $this->assertStringContainsString('性別', $csv);
        $this->assertStringContainsString('メールアドレス', $csv);
        $this->assertStringContainsString('電話番号', $csv);
        $this->assertStringContainsString('住所', $csv);
        $this->assertStringContainsString('建物名', $csv);
        $this->assertStringContainsString('お問合せの種類', $csv);
        $this->assertStringContainsString('お問合せ内容', $csv);
        $this->assertStringContainsString('作成日時', $csv);

        $this->assertStringContainsString('佐藤 花子', $csv);
        $this->assertStringContainsString('女性', $csv);

        $newerPosition = strpos($csv, 'newer@example.com');
        $otherPosition = strpos($csv, 'other@example.com');
        $olderPosition = strpos($csv, 'older@example.com');

        $this->assertLessThan($otherPosition, $newerPosition);
        $this->assertLessThan($olderPosition, $otherPosition);
    }

    private function validContact(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'first_name' => '花子',
            'last_name' => '佐藤',
            'gender' => 2,
            'email' => 'hanako@example.com',
            'tel' => '09012345678',
            'address' => '東京都',
            'building' => null,
            'detail' => '商品について問い合わせます。',
        ], $overrides);
    }
}
