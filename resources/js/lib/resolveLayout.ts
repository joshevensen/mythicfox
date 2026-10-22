export type LayoutKey = 'none' | 'public' | 'auth' | 'settings' | 'admin';

/**
 * Maps an Inertia page name to the layout it renders in.
 *
 * Unknown or newly added pages default to the admin shell on purpose, so a
 * renamed page can never silently fall into a different shell.
 */
export function resolveLayout(name: string): LayoutKey {
    if (name === 'Welcome' || name === 'Orders/PackingSlip') {
        return 'none';
    }

    if (name.startsWith('public/')) {
        return 'public';
    }

    if (name.startsWith('auth/')) {
        return 'auth';
    }

    if (name.startsWith('settings/')) {
        return 'settings';
    }

    return 'admin';
}
