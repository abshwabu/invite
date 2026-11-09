<script setup>
import { ref, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const showingNavigationDropdown = ref(false);

const leaveImpersonation = () => {
    router.post(route('impersonation.leave'));
};

onMounted(async () => {
    // Check if user has an unimported guest draft in localStorage
    try {
        const stored = localStorage.getItem('invite_guest_draft');
        if (stored) {
            const draft = JSON.parse(stored);
            if (draft && draft.design_json) {
                await axios.post(route('events.import-draft'), { draft });
                localStorage.removeItem('invite_guest_draft');
                router.reload();
            }
        }
    } catch (e) {
        // silent fail
    }
});
</script>

<template>
    <div>
        <!-- Impersonation Banner -->
        <div
            v-if="$page.props.impersonation?.active"
            class="sticky top-0 z-50 bg-amber-600 text-white px-4 py-2 text-xs flex items-center justify-between font-semibold shadow-md"
        >
            <div class="flex items-center gap-2">
                <span class="text-base">🕵️</span>
                <span>
                    You are impersonating <strong>{{ $page.props.auth.user.name }}</strong>
                    ({{ $page.props.impersonation.mode === 'read_only' ? 'Read-Only Mode' : 'Full Session' }} by {{ $page.props.impersonation.admin_name }})
                </span>
            </div>
            <button
                @click="leaveImpersonation"
                class="px-3 py-1 rounded-lg bg-black/25 hover:bg-black/40 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm"
            >
                <span>Exit Impersonation ✕</span>
            </button>
        </div>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <nav
                class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-800"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between items-center">
                        <div class="flex items-center">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-2">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-amber-600"
                                    />
                                    <span class="font-bold text-lg text-gray-900 dark:text-white">Invite</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex items-center"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    {{ $t('Dashboard') }}
                                </NavLink>
                                <NavLink
                                    :href="route('templates.index')"
                                    :active="route().current('templates.*')"
                                >
                                    {{ $t('Gallery') }}
                                </NavLink>
                                <NavLink
                                    :href="route('editor.show')"
                                    :active="route().current('editor.*')"
                                >
                                    {{ $t('Editor') }}
                                </NavLink>
                                <NavLink
                                    :href="route('plan.index')"
                                    :active="route().current('plan.*')"
                                >
                                    {{ $t('My Plan') }}
                                </NavLink>
                                <NavLink
                                    :href="route('usage.index')"
                                    :active="route().current('usage.*')"
                                >
                                    {{ $t('Usage') }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3">
                            <!-- Notification Bell -->
                            <Link
                                :href="route('notifications.index')"
                                class="relative p-2 rounded-full text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition"
                                :title="$t('Notifications')"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span
                                    v-if="$page.props.auth?.unreadNotificationsCount > 0"
                                    class="absolute top-1 right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-rose-600 rounded-full ring-2 ring-white dark:ring-gray-800"
                                >
                                    {{ $page.props.auth.unreadNotificationsCount > 99 ? '99+' : $page.props.auth.unreadNotificationsCount }}
                                </span>
                            </Link>

                            <!-- Quota Quick Pill -->
                            <Link
                                v-if="$page.props.auth?.quota"
                                :href="route('plan.index')"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition"
                                :class="$page.props.auth.quota.is_exhausted
                                    ? 'bg-rose-100 text-rose-800 hover:bg-rose-200 dark:bg-rose-950/60 dark:text-rose-300'
                                    : ($page.props.auth.quota.is_low
                                        ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-950/60 dark:text-amber-300'
                                        : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800')"
                            >
                                <span>✉️</span>
                                <span>{{ $page.props.auth.quota.available }} {{ $t('cards') }}</span>
                            </Link>

                            <!-- Language Switcher -->
                            <LanguageSwitcher />

                            <!-- Settings Dropdown -->
                            <div class="relative ms-1">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium leading-4 text-gray-700 dark:text-gray-200 transition duration-150 ease-in-out hover:text-gray-900 dark:hover:text-white focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('plan.index')"
                                        >
                                            {{ $t('My Plan') }}
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('usage.index')"
                                        >
                                            {{ $t('My Usage') }}
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('notifications.index')"
                                        >
                                            {{ $t('Notifications') }}
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            {{ $t('Profile') }}
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            {{ $t('Log Out') }}
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center gap-2 sm:hidden">
                            <LanguageSwitcher />

                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:hover:bg-gray-700"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            {{ $t('Dashboard') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('templates.index')"
                            :active="route().current('templates.*')"
                        >
                            {{ $t('Gallery') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('editor.show')"
                            :active="route().current('editor.*')"
                        >
                            {{ $t('Editor') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('plan.index')"
                            :active="route().current('plan.*')"
                        >
                            {{ $t('My Plan') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('usage.index')"
                            :active="route().current('usage.*')"
                        >
                            {{ $t('Usage') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('notifications.index')"
                            :active="route().current('notifications.*')"
                        >
                            {{ $t('Notifications') }}
                            <span
                                v-if="$page.props.auth?.unreadNotificationsCount > 0"
                                class="ms-2 px-1.5 py-0.5 text-xs rounded-full bg-rose-600 text-white font-bold"
                            >
                                {{ $page.props.auth.unreadNotificationsCount }}
                            </span>
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4 dark:border-gray-700"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email || $page.props.auth.user.phone }}
                            </div>
                            <div v-if="$page.props.auth?.quota" class="mt-2">
                                <span class="text-xs px-2.5 py-1 rounded-full font-semibold"
                                    :class="$page.props.auth.quota.is_exhausted ? 'bg-rose-100 text-rose-800' : ($page.props.auth.quota.is_low ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')">
                                    {{ $page.props.auth.quota.available }} {{ $t('cards available') }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('plan.index')">
                                {{ $t('My Plan') }}
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('profile.edit')">
                                {{ $t('Profile') }}
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                {{ $t('Log Out') }}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Flash & System Notifications -->
            <div v-if="$page.props.flash?.success || $page.props.flash?.warning || $page.props.flash?.error" class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash.success" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-xs sm:text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash.warning" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs sm:text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200 flex items-center justify-between">
                    <span>{{ $page.props.flash.warning }}</span>
                    <Link :href="route('plan.index')" class="ml-3 font-bold underline hover:text-amber-900">{{ $t('Upgrade') }}</Link>
                </div>
                <div v-if="$page.props.flash.error" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs sm:text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-200">
                    {{ $page.props.flash.error }}
                </div>
            </div>

            <!-- Page Heading -->
            <header
                class="bg-white shadow dark:bg-gray-800"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
