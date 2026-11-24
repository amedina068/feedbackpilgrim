<template>
    <Menu as="div" class="relative">
        <MenuButton class="flex rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">
            <slot name="handler" />
        </MenuButton>

        <MenuItems class="absolute right-0 z-10 mt-1 flex w-56 flex-col rounded-md bg-fp-elevated py-1 ring-1 ring-white/10 focus:outline-hidden">
            <slot />
        </MenuItems>
    </Menu>
</template>

<script setup>
import { Menu, MenuButton, MenuItems } from "@headlessui/vue";
</script>
