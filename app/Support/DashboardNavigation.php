<?php

namespace App\Support;

use App\Models\User;

/**
 * Extra links for the dashboard navigation.
 *
 * A hosted edition or a plugin adds links in the boot() method of its service
 * provider. The sidebar and the mobile menu show the links after the core
 * links. With no links, the navigation markup does not change.
 */
class DashboardNavigation
{
    /** @var list<array{label: string, url: string, visible: (callable(User): bool)|null, icon: string|null}> */
    private array $items = [];

    /**
     * Add a link. The label is escaped when the dashboard shows it. When
     * `$visible` is set, the dashboard shows the link only if it returns true.
     * `$icon` is the name of a Blade component, for example `cloud::billing-icon`.
     * The dashboard shows it before the label, like the icons of the core links.
     *
     * @param  (callable(User): bool)|null  $visible
     */
    public function add(string $label, string $url, ?callable $visible = null, ?string $icon = null): static
    {
        $this->items[] = ['label' => $label, 'url' => $url, 'visible' => $visible, 'icon' => $icon];

        return $this;
    }

    /**
     * The links that the user can see, in the order that they were added.
     *
     * @return list<array{label: string, url: string, icon: string|null}>
     */
    public function items(User $user): array
    {
        $items = [];

        foreach ($this->items as $item) {
            if ($item['visible'] === null || ($item['visible'])($user) === true) {
                $items[] = ['label' => $item['label'], 'url' => $item['url'], 'icon' => $item['icon']];
            }
        }

        return $items;
    }
}
