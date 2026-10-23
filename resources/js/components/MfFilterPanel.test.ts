import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import type { FilterDef } from '@/components/MfFilter.types';
import MfFilterPanel from '@/components/MfFilterPanel.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
    usePage: () => ({ url: '/catalog' }),
}));

// PrimeVue declares props on an extended base component, which auto-stubs
// don't copy, so the stubs declare the props under test explicitly.
const controlStub = (name: string) =>
    defineComponent({
        name,
        props: ['modelValue', 'options', 'filter', 'disabled', 'placeholder'],
        render: () => h('div'),
    });

const MultiSelect = controlStub('MultiSelect');
const Select = controlStub('Select');

const mountPanel = (filters: FilterDef[]) =>
    mount(MfFilterPanel, {
        props: { filters },
        global: {
            stubs: {
                MultiSelect,
                Select,
                InputNumber: true,
                ToggleSwitch: true,
            },
        },
    });

const enumDef = (overrides: Partial<FilterDef> = {}): FilterDef => ({
    kind: 'enum',
    key: 'sets',
    label: 'Set',
    options: [],
    ...overrides,
});

describe('MfFilterPanel', () => {
    it('passes searchable through as the MultiSelect filter prop', () => {
        const wrapper = mountPanel([
            enumDef({ searchable: true }),
            enumDef({ key: 'rarities', label: 'Rarity' }),
        ]);
        const [searchable, plain] = wrapper.findAllComponents(MultiSelect);

        expect(searchable.props('filter')).toBe(true);
        expect(plain.props('filter')).toBeFalsy();
    });

    it('disables the MultiSelect and shows the disabled placeholder', () => {
        const wrapper = mountPanel([
            enumDef({
                disabled: true,
                disabledPlaceholder: 'Select a product first',
            }),
        ]);
        const multiSelect = wrapper.findComponent(MultiSelect);

        expect(multiSelect.props('disabled')).toBe(true);
        expect(multiSelect.props('placeholder')).toBe('Select a product first');
    });

    it('falls back to the label when disabled without a placeholder', () => {
        const wrapper = mountPanel([enumDef({ disabled: true })]);

        expect(wrapper.findComponent(MultiSelect).props('placeholder')).toBe(
            'Set',
        );
    });

    it('leaves the MultiSelect enabled with the label placeholder by default', () => {
        const wrapper = mountPanel([
            enumDef({ disabledPlaceholder: 'Select a product first' }),
        ]);
        const multiSelect = wrapper.findComponent(MultiSelect);

        expect(multiSelect.props('disabled')).toBeFalsy();
        expect(multiSelect.props('placeholder')).toBe('Set');
    });

    it('disables a select filter', () => {
        const wrapper = mountPanel([
            {
                kind: 'select',
                key: 'product',
                label: 'Product',
                options: [],
                disabled: true,
            },
        ]);

        expect(wrapper.findComponent(Select).props('disabled')).toBe(true);
    });
});
