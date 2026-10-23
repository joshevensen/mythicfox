import { describe, expect, it } from 'vitest';
import { resolveLayout } from '@/lib/resolveLayout';

describe('resolveLayout', () => {
    it.each([
        'Catalog/Sets',
        'Catalog/Cards',
        'Catalog/Empty',
        'Catalog/RowExpand',
        'Dashboard',
        'Orders/Index',
        'Orders/OrderItemsPanel',
        'placeholders/ComingSoon',
    ])('renders %s in the admin shell', (name) => {
        expect(resolveLayout(name)).toBe('admin');
    });

    it.each([
        'settings/Profile',
        'settings/Security',
        'settings/Appearance',
        'settings/Products',
        'settings/Files',
    ])('renders %s in the settings stack', (name) => {
        expect(resolveLayout(name)).toBe('settings');
    });

    it('renders auth pages in the auth layout', () => {
        expect(resolveLayout('auth/Login')).toBe('auth');
    });

    it.each(['public/Home', 'public/Login', 'public/Terms'])(
        'renders %s in the public layout',
        (name) => {
            expect(resolveLayout(name)).toBe('public');
        },
    );

    it.each(['Welcome', 'Orders/PackingSlip'])(
        'renders %s without a layout',
        (name) => {
            expect(resolveLayout(name)).toBe('none');
        },
    );

    it.each(['Cards/Index', 'Foo/Bar', 'SomethingNew'])(
        'defaults unknown page %s to the admin shell',
        (name) => {
            expect(resolveLayout(name)).toBe('admin');
        },
    );
});
