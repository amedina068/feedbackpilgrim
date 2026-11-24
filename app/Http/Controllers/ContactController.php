<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactAttribute;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = Contact::query()
            ->whereNull('user_id')
            ->when(
                $this->sort($request),
                fn ($query, array $sort) => $query->orderBy(...$sort),
                fn ($query) => $query->latest('contacts.created_at')
            )
            ->when(
                $request->filled('tags'),
                fn ($query) => $query->join(
                    'taggables',
                    fn ($query) => $query->on('contacts.id', 'taggables.taggable_id')
                        ->where('taggables.taggable_type', Contact::class)
                )
                    ->whereIn('taggables.tag_id', $request->tags)
            )
            ->when(
                $request->filled('attributes'),
                function ($query) use ($request) {
                    foreach ($request->input('attributes') as $key => $value) {
                        $query->where("metadata->{$key}", $value);
                    }
                }
            )
            ->with(['tags'])
            ->where('contacts.client_id', $request->attributes->get('fp_client_id'))
            ->withCount(['posts', 'comments', 'votes'])
            ->paginate();

        $topLtvContacts = Contact::query()
            ->where('contacts.client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('ltv', 'DESC')
            ->select('id')
            ->limit(10)
            ->pluck('id');

        $topMrrContacts = Contact::query()
            ->where('contacts.client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('mrr', 'DESC')
            ->select('id')
            ->limit(10)
            ->pluck('id');

        foreach ($contacts->items() as $item) {
            $item->setAttribute('top_mrr', (bool) $topMrrContacts->first(fn ($topMrrContact) => $topMrrContact == $item->id));
            $item->setAttribute('top_ltv', (bool) $topLtvContacts->first(fn ($topLtvContact) => $topLtvContact == $item->id));
        }

        $tags = Tag::query()->where('client_id', $request->attributes->get('fp_client_id'))->orderBy('title')->get();

        $attributes = ContactAttribute::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('name')
            ->get();

        return view('contacts.index', compact('contacts', 'tags', 'attributes'));
    }

    /**
     * The sorts of the contacts page: the `sort_by` value, the column and the direction.
     */
    private const SORTS = [
        'ltv__DESC' => ['contacts.ltv', 'desc'],
        'ltv__ASC' => ['contacts.ltv', 'asc'],
        'mrr__DESC' => ['contacts.mrr', 'desc'],
        'mrr__ASC' => ['contacts.mrr', 'asc'],
        'created_at__DESC' => ['contacts.created_at', 'desc'],
        'created_at__ASC' => ['contacts.created_at', 'asc'],
        'posts_count__DESC' => ['posts_count', 'desc'],
        'posts_count__ASC' => ['posts_count', 'asc'],
    ];

    /**
     * The column and the direction of a known `sort_by` value, or null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function sort(Request $request): ?array
    {
        $sortBy = $request->query('sort_by');

        return is_string($sortBy) ? (self::SORTS[$sortBy] ?? null) : null;
    }

    public function show(Request $request, $contactId)
    {
        $contact = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->withCount(['votes', 'comments'])
            ->with([
                'comments' => fn ($query) => $query->latest()->with(['commentable'])->take(5),
                'tags',
                'votes' => fn ($query) => $query->latest()->take(5)->with(['votable']),
                'posts' => fn ($query) => $query->latest()->take(5),
            ])
            ->findOrFail($contactId);

        $sessions = Activity::query()
            ->where([
                'client_id' => $request->attributes->get('fp_client_id'),
                'causer_id' => $contact->id,
                'causer_type' => get_class($contact),
                'description' => 'login',
            ])->count();

        $topLtvContacts = Contact::query()
            ->where('contacts.client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('ltv', 'DESC')
            ->whereNotNull('ltv')
            ->where('ltv', '!=', 0)
            ->select('id')
            ->limit(10)
            ->pluck('id');

        $topMrrContacts = Contact::query()
            ->where('contacts.client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('mrr', 'DESC')
            ->whereNotNull('mrr')
            ->where('mrr', '!=', 0)
            ->select('id')
            ->limit(10)
            ->pluck('id');

        $contact->setAttribute('top_mrr', (bool) $topMrrContacts->first(fn ($topMrrContact) => $topMrrContact == $contact->id));
        $contact->setAttribute('top_ltv', (bool) $topLtvContacts->first(fn ($topLtvContact) => $topLtvContact == $contact->id));

        return view('contacts.show', compact('contact', 'sessions'));
    }

    public function edit(Request $request, $contactId)
    {
        $contact = Contact::query()
            ->with('tags')
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($contactId);

        $tags = Tag::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->get();

        return view('contacts.edit', compact('contact', 'tags'));
    }

    public function update(Request $request, $contactId)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'ltv' => ['nullable', 'numeric', 'min:0'],
            'mrr' => ['nullable', 'numeric', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        $contact = Contact::query()->where('client_id', $request->attributes->get('fp_client_id'))->findOrFail($contactId);

        $contact->update(array_merge($request->only('email', 'name', 'notes', 'mrr', 'ltv'), [
            'metadata' => $request->filled('custom_fields')
                ? json_encode(array_merge($request->custom_fields, $contact->pilgrimFields), true)
                : $contact->pilgrimFields,
        ], [
            'top_customer' => $request->boolean('top_customer'),
        ]));

        $contact->tags()->sync($request->input('tags', []));

        return redirect('/contacts/'.$contact->id)->with('success', 'Contact updated successfully');
    }
}
