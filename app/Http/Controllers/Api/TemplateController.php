<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return TemplateResource::collection(
            $request->user()->templates()->orderBy('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $template = $request->user()->templates()->create($this->validated($request));

        return (new TemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(Request $request, Template $template): TemplateResource
    {
        abort_unless($template->user_id === $request->user()->id, 404);

        $template->update($this->validated($request, partial: true));

        return new TemplateResource($template);
    }

    /**
     * Deleting a template leaves its entries alone — `template_id` is nulled by
     * the foreign key, and `template_name` on each entry keeps the label that
     * was used at the time.
     */
    public function destroy(Request $request, Template $template): JsonResponse
    {
        abort_unless($template->user_id === $request->user()->id, 404);

        $template->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        $validated = $request->validate([
            'id' => ['sometimes', 'uuid'],
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
            'fields' => [$presence, 'array', 'min:1'],
            'fields.*.id' => ['required', 'string', 'max:255'],
            'fields.*.label' => ['required', 'string', 'max:500'],
            'fields.*.multiline' => ['nullable', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'builtIn' => ['nullable', 'boolean'],
        ]);

        // The app speaks camelCase; the column does not.
        if (array_key_exists('builtIn', $validated)) {
            $validated['built_in'] = $validated['builtIn'];
            unset($validated['builtIn']);
        }

        return $validated;
    }
}
