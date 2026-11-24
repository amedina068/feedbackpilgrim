<?php

namespace App\View\Components;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class GenericVoteForm extends Component
{
    public $contact;

    public $hasVoted;

    public $votesCount;

    /**
     * Create a new component instance.
     */
    public function __construct(
        public Model $model,
        public Client $client
    ) {
        $this->contact = Auth::guard('contact')->user();

        $this->hasVoted = $this->contact
            ? $model->votes()->where('contact_id', $this->contact->id)->exists()
            : false;

        $this->votesCount = $model->votes_count;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.generic-vote-form');
    }
}
