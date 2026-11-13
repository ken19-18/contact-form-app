<?php

namespace Tests\Unit;

use App\Http\Requests\Api\V1\IndexContactRequest as ApiIndexContactRequest;
use App\Http\Requests\Api\V1\StoreContactRequest as ApiStoreContactRequest;
use App\Http\Requests\IndexContactRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ContactRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_filters_accept_valid_search_values(): void
    {
        $category = Category::create(['content' => '注文について']);
        $request = new IndexContactRequest;
        $validator = Validator::make([
            'keyword' => '佐藤',
            'gender' => '2',
            'category_id' => $category->id,
            'date' => '2026-11-13',
        ], $request->rules());

        $this->assertTrue($validator->passes());
        $this->assertTrue(Validator::make([
            'gender' => '1',
            'category_id' => $category->id + 1000,
        ], $request->rules())->fails());
        $this->assertTrue(Validator::make([
            'gender' => '9',
        ], $request->rules())->fails());
    }

    public function test_web_contact_search_validation_rejects_invalid_gender_and_date(): void
    {
        $category = Category::create(['content' => '注文について']);
        $validator = Validator::make([
            'keyword' => ['検索語'],
            'gender' => '9',
            'category_id' => $category->id,
            'date' => 'not-a-date',
        ], (new IndexContactRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
        $this->assertArrayHasKey('date', $validator->errors()->toArray());
    }

    public function test_web_contact_save_validation_requires_fields_and_validates_phone(): void
    {
        $category = Category::create(['content' => '注文について']);
        $valid = $this->validContact($category);
        $this->assertTrue(Validator::make($valid, (new StoreContactRequest)->rules())->passes());
        $valid['tel'] = '03-1234-5678';
        $valid['email'] = 'invalid';
        $this->assertTrue(Validator::make($valid, (new StoreContactRequest)->rules())->fails());

        $empty = [];
        $validator = Validator::make($empty, (new StoreContactRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('first_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('last_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('tel', $validator->errors()->toArray());
    }

    public function test_tag_create_validation_enforces_required_maximum_and_uniqueness(): void
    {
        Tag::create(['name' => '既存タグ']);
        $rules = (new StoreTagRequest)->rules();
        $this->assertTrue(Validator::make(['name' => '新しいタグ'], $rules)->passes());
        $this->assertTrue(Validator::make(['name' => '既存タグ'], $rules)->fails());
        $this->assertTrue(Validator::make(['name' => str_repeat('あ', 51)], $rules)->fails());
    }

    public function test_tag_update_validation_allows_own_name_and_rejects_another_tag_name(): void
    {
        $tag = Tag::create(['name' => '保持する名前']);
        Tag::create(['name' => '他の名前']);
        $request = new UpdateTagRequest;
        $route = new Route('PUT', '/admin/tags/{tag}', []);

        $route->bind(Request::create('/admin/tags/'.$tag->id, 'PUT'));

        $route->setParameter('tag', $tag);

        $request->setRouteResolver(fn () => $route);

        $this->assertTrue(Validator::make(['name' => $tag->name], $request->rules())->passes());
        $this->assertTrue(Validator::make(['name' => '他の名前'], $request->rules())->fails());
    }

    public function test_category_has_many_contacts_relation(): void
    {
        $category = Category::create(['content' => '注文について']);
        $first = Contact::create($this->validContact($category));
        $second = Contact::create($this->validContact($category, ['email' => 'second@example.com']));

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $category->contacts->modelKeys());
    }

    public function test_contact_belongs_to_category_and_syncs_tags(): void
    {
        $category = Category::create(['content' => '注文について']);
        $contact = Contact::create($this->validContact($category));
        $tag = Tag::create(['name' => '要望']);
        $contact->tags()->sync([$tag->id]);

        $this->assertSame($category->id, $contact->category->id);
        $this->assertSame([$tag->id], $contact->fresh()->tags->modelKeys());
    }

    public function test_tag_belongs_to_many_contacts(): void
    {
        $category = Category::create(['content' => '注文について']);
        $contact = Contact::create($this->validContact($category));
        $tag = Tag::create(['name' => '要望']);
        $contact->tags()->attach($tag);

        $this->assertSame([$contact->id], $tag->fresh()->contacts->modelKeys());
    }

    public function test_api_search_validation_accepts_filters_and_rejects_invalid_pagination(): void
    {
        $category = Category::create(['content' => '注文について']);
        $rules = (new ApiIndexContactRequest)->rules();
        $this->assertTrue(Validator::make(['gender' => 1, 'category_id' => $category->id, 'date' => '2026-11-13', 'per_page' => 25], $rules)->passes());
        $this->assertTrue(Validator::make(['gender' => 9, 'page' => 0, 'per_page' => 101], $rules)->fails());
        $this->assertTrue(Validator::make([
            'category_id' => 99999,
        ], $rules)->fails());

        $this->assertTrue(Validator::make([
            'date' => 'not-a-date',
        ], $rules)->fails());
    }

    public function test_api_contact_create_validation_rejects_invalid_required_values(): void
    {
        $category = Category::create(['content' => '注文について']);
        $values = $this->validContact($category);
        $this->assertTrue(Validator::make($values, (new ApiStoreContactRequest)->rules())->passes());
        $tag = Tag::create(['name' => 'APIテストタグ']);
        $values['tag_ids'] = [$tag->id];

        $this->assertTrue(
            Validator::make($values, (new ApiStoreContactRequest)->rules())->passes()
        );
        $values['tag_ids'] = [99999];

        $validator = Validator::make(
            $values,
            (new ApiStoreContactRequest)->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('tag_ids.0', $validator->errors()->toArray());
        $values['tel'] = '123';
        $values['gender'] = 8;
        $this->assertTrue(Validator::make($values, (new ApiStoreContactRequest)->rules())->fails());
        $empty = [];
        $validator = Validator::make($empty, (new ApiStoreContactRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('first_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('last_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('tel', $validator->errors()->toArray());
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
