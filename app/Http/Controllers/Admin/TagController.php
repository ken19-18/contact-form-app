<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Models\Tag;

class TagController extends Controller
{
    public function edit(Tag $tag)
    {
        return view('admin.tags.edit', compact('tag'));
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $validated = $request->validated();

        $tag->update($validated);

        return redirect('/admin');
    }

    public function store(StoreTagRequest $request)
    {
        $validated = $request->validated();

        Tag::create($validated);

        return redirect('/admin');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return redirect('/admin');
    }
}
