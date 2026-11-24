<?php

namespace App\Http\Resources;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ReleaseNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'created_at' => $this->created_at,
            'content_excerpt' => Str::limit($this->content, 100),
            'readable_published_at' => $this->readableDate($this->published_at),
        ];
    }

    public function readableDate(DateTime $dateTime)
    {
        return $dateTime->format('M jS, Y');
    }
}
