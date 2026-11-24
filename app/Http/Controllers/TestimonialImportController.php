<?php

namespace App\Http\Controllers;

use App\Actions\Testimonials\ImportTestimonials;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestimonialImportController extends Controller
{
    public function __construct(private ImportTestimonials $importTestimonials) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv', 'max:2048'],
        ], [
            'file.mimetypes' => 'The file must be a CSV file.',
        ]);

        $client = Client::findOrFail($request->attributes->get('fp_client_id'));

        $result = ($this->importTestimonials)(
            $request->file('file')->getRealPath(),
            $client,
        );

        return redirect()->route('testimonials.index')
            ->with('success', "Imported {$result['imported']} testimonials ({$result['duplicates']} already imported, {$result['skipped']} skipped).");
    }
}
