<?php

namespace App\View\Components;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

class PublicCommentForm extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public Client $client,
        public Model $model,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.public-comment-form');
    }
}
