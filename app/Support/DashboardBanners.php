<?php

namespace App\Support;

use App\Models\User;
use UnexpectedValueException;

/**
 * One-line notices at the top of the dashboard.
 *
 * A hosted edition or a plugin adds resolvers in the boot() method of its
 * service provider. A resolver gets the user and returns null (no banner) or
 * an array with these keys:
 *
 * - `message`: the text of the banner. This key is necessary.
 * - `url`: an optional link target. It must start with `/`, `http://` or `https://`.
 * - `link`: an optional link text. The default is "Details".
 *
 * The dashboard escapes all the values. It shows no banner markup when all
 * the resolvers return null.
 */
class DashboardBanners
{
    /** The link text of a banner that has a URL and no link text. */
    public const DEFAULT_LINK = 'Details';

    /** @var list<callable(User): (array<string, mixed>|null)> */
    private array $resolvers = [];

    /**
     * @param  callable(User): (array<string, mixed>|null)  $resolver
     */
    public function add(callable $resolver): static
    {
        $this->resolvers[] = $resolver;

        return $this;
    }

    /**
     * The banners for the user, in the order that the resolvers were added.
     *
     * @return list<array{message: string, url: string|null, link: string|null}>
     *
     * @throws UnexpectedValueException when a resolver returns a banner without a message or with an unsafe URL
     */
    public function items(User $user): array
    {
        $banners = [];

        foreach ($this->resolvers as $resolver) {
            $banner = $resolver($user);

            if ($banner !== null) {
                $banners[] = $this->normalize($banner);
            }
        }

        return $banners;
    }

    /**
     * @param  array<string, mixed>  $banner
     * @return array{message: string, url: string|null, link: string|null}
     */
    private function normalize(array $banner): array
    {
        $message = $banner['message'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            throw new UnexpectedValueException('A dashboard banner must have a "message" string.');
        }

        $url = $banner['url'] ?? null;

        if ($url !== null && (! is_string($url) || preg_match('#^(https?://|/(?!/))#i', $url) !== 1)) {
            throw new UnexpectedValueException('The "url" of a dashboard banner must start with "/", "http://" or "https://".');
        }

        $link = $banner['link'] ?? null;

        if ($link !== null && ! is_string($link)) {
            throw new UnexpectedValueException('The "link" of a dashboard banner must be a string.');
        }

        return [
            'message' => $message,
            'url' => $url,
            'link' => $url === null ? null : ($link ?? self::DEFAULT_LINK),
        ];
    }
}
