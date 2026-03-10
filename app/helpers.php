<?php

if (! function_exists('auth_context')) {
    /**
     * Get a formatted log context string for the authenticated user.
     */
    function auth_context(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'User [unauthenticated]';
        }

        return "User {$user->id} ({$user->name})";
    }
}
