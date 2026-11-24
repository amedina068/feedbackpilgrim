<?php

namespace App\Http\Controllers;

use App\Mcp\Support\ToolCatalog;
use App\Support\ApiReference;
use App\Support\Guides;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentationController extends Controller
{
    public function index(): View
    {
        return view('documentation.guides.index', [
            'groupedGuides' => Guides::grouped(),
        ]);
    }

    public function guide(string $slug): View
    {
        $guide = Guides::find($slug);

        abort_if($guide === null, 404);

        return view('documentation.guides.show', [
            'guide' => $guide,
            'groupedGuides' => Guides::grouped(),
        ]);
    }

    public function reference(): View
    {
        return view('documentation.reference', [
            'reference' => ApiReference::fromPublicPath('docs/openapi.json'),
            'mcpTools' => ToolCatalog::all(),
        ]);
    }

    public function openApi(): Response
    {
        $path = public_path('docs/openapi.json');

        abort_unless(file_exists($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function downloadOpenApi(): BinaryFileResponse
    {
        $path = public_path('docs/openapi.json');

        abort_unless(file_exists($path), 404);

        return response()->download($path, 'feedbackpilgrim-openapi.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * "composer api-docs" makes the collection from docs/api/openapi.yaml.
     */
    public function downloadPostman(): BinaryFileResponse
    {
        return response()->download(base_path('docs/api/postman_collection.json'), 'feedbackpilgrim.postman_collection.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}
