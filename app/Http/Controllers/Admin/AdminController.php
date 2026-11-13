<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(IndexContactRequest $request)
    {
        $contacts = $this->contactQuery($request)
            ->latest()
            ->paginate(7);

        $categories = Category::all();
        $tags = Tag::all();

        return view('admin.index', compact('contacts', 'categories', 'tags'));
    }

    public function show(Contact $contact)
    {
        $contact->load(['category', 'tags']);

        return view('admin.show', compact('contact'));
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return redirect('/admin');
    }

    public function export(Request $request)
    {
        $contacts = $this->contactQuery($request)->latest()->get();

        $csvHeader = [
            'ID',
            '氏名',
            '性別',
            'メールアドレス',
            '電話番号',
            '住所',
            '建物名',
            'お問合せの種類',
            'お問合せ内容',
            '作成日時',
        ];

        $callback = function () use ($contacts, $csvHeader) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, $csvHeader);

            foreach ($contacts as $contact) {
                fputcsv($file, [
                    $contact->id,
                    $contact->last_name.' '.$contact->first_name,
                    [1 => '男性', 2 => '女性', 3 => 'その他'][$contact->gender] ?? '',
                    $contact->email,
                    $contact->tel,
                    $contact->address,
                    $contact->building,
                    $contact->category->content ?? '',
                    $contact->detail,
                    $contact->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->streamDownload(
            $callback,
            'contacts.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function contactQuery(Request $request)
    {
        $query = Contact::with(['category', 'tags']);

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                    ->orWhere('last_name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('gender') && $request->gender != 0) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return $query;
    }
}
