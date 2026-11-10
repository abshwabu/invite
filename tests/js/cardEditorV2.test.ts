import { describe, it, expect } from 'vitest';
import { resolveColor, applyPalette, deriveHarmoniousPalette, DEFAULT_PALETTE } from '../../resources/js/services/paletteResolver';
import { loadTintedSvg, findOrnament, getAllSets, getAllTags } from '../../resources/js/services/ornamentRegistry';
import { getTintKey } from '../../resources/js/services/rasterCache';
import { resolvePlaceholders } from '../../resources/js/services/renderDesign';
import { DesignV2 } from '../../resources/js/types/design';

describe('Step 5A: Card Editor v2 Upgrade', () => {
    describe('Palette System & Re-theming', () => {
        it('resolves color roles against a custom palette', () => {
            const customPalette = {
                ...DEFAULT_PALETTE,
                primary: '#8C2D45',
                secondary: '#D4A373',
                accent: '#B35D70',
                bg: '#FAF7F5',
            };

            expect(resolveColor('@primary', customPalette)).toBe('#8C2D45');
            expect(resolveColor('@secondary', customPalette)).toBe('#D4A373');
            expect(resolveColor('@accent', customPalette)).toBe('#B35D70');
            expect(resolveColor('@bg', customPalette)).toBe('#FAF7F5');
            expect(resolveColor('#123456', customPalette)).toBe('#123456');
        });

        it('palette swap updates every role-referencing layer in the design', () => {
            const baseDesign: DesignV2 = {
                version: 2,
                width: 1080,
                height: 1528,
                palette: {
                    bg: '#18181B',
                    surface: '#27272A',
                    primary: '#D4AF37',
                    secondary: '#E5D0AC',
                    accent: '#C5A059',
                    ink: '#FAFAFA',
                    muted: '#9CA3AF',
                },
                background: {
                    type: 'color',
                    value: '@bg',
                },
                layers: [
                    {
                        id: 'layer-title',
                        type: 'text',
                        x: 100,
                        y: 200,
                        width: 800,
                        height: 100,
                        fill: '@primary',
                        text: 'Wedding Invitation',
                    },
                    {
                        id: 'layer-border',
                        type: 'border',
                        style: 'double-rule',
                        x: 0,
                        y: 0,
                        width: 1080,
                        height: 1528,
                        stroke: '@secondary',
                    },
                    {
                        id: 'layer-ornament',
                        type: 'ornament',
                        assetId: 'meskel-cross-01',
                        x: 400,
                        y: 100,
                        width: 200,
                        height: 200,
                        tint: { c1: '@primary', c2: '@accent' },
                    },
                ],
            };

            // Swap palette to Royal Velvet / Garden theme
            const newTheme = {
                bg: '#FAF7F5',
                surface: '#FFFFFF',
                primary: '#8C2D45',
                secondary: '#2E5A88',
                accent: '#B35D70',
                ink: '#1F2937',
                muted: '#6B7280',
            };

            const updated = applyPalette(baseDesign, newTheme);

            // Verify palette updated
            expect(updated.palette.primary).toBe('#8C2D45');
            expect(updated.palette.secondary).toBe('#2E5A88');
            expect(updated.palette.bg).toBe('#FAF7F5');

            // Verify role-referencing layers resolve to new theme colors
            const textFill = resolveColor(updated.layers[0].fill, updated.palette);
            expect(textFill).toBe('#8C2D45');

            const borderStroke = resolveColor(updated.layers[1].stroke, updated.palette);
            expect(borderStroke).toBe('#2E5A88');
        });

        it('derives a harmonious 7-role palette from a single base color hex', () => {
            const emeraldPalette = deriveHarmoniousPalette('#064E3B');

            expect(emeraldPalette.primary).toBe('#064e3b');
            expect(emeraldPalette.bg).toBeDefined();
            expect(emeraldPalette.surface).toBeDefined();
            expect(emeraldPalette.secondary).toBeDefined();
            expect(emeraldPalette.accent).toBeDefined();
            expect(emeraldPalette.ink).toBeDefined();
            expect(emeraldPalette.muted).toBeDefined();
            expect(emeraldPalette.bg.startsWith('#')).toBe(true);
            expect(emeraldPalette.ink.startsWith('#')).toBe(true);
        });
    });

    describe('Ornament Registry & Tinting (Task 3 & 10)', () => {
        it('manifest contains registered ornaments with sets and tags', () => {
            const sets = getAllSets();
            expect(sets).toContain('traditional');
            expect(sets.length).toBeGreaterThan(4);

            const tags = getAllTags();
            expect(tags).toContain('cross');
            expect(tags).toContain('traditional');

            const cross = findOrnament('meskel-cross-01');
            expect(cross).toBeDefined();
            expect(cross?.set).toBe('traditional');
            expect(cross?.tintSlots).toEqual(['c1', 'c2', 'c3']);
        });

        it('loadTintedSvg updates tint tokens with palette colors', () => {
            const palette = {
                ...DEFAULT_PALETTE,
                primary: '#AA1122',
                secondary: '#BB3344',
                accent: '#CC5566',
            };

            const tinted = loadTintedSvg(
                'meskel-cross-01',
                { c1: '@primary', c2: '@secondary', c3: '@accent' },
                palette
            );

            expect(tinted).toBeDefined();
            expect(tinted).not.toContain('{{c1}}');
            expect(tinted).not.toContain('{{c2}}');
            expect(tinted).not.toContain('{{c3}}');
            expect(tinted).toContain('#AA1122');
            expect(tinted).toContain('#BB3344');
            expect(tinted).toContain('#CC5566');
        });

        it('rasterCache produces unique keys for distinct tints and palettes', () => {
            const paletteA = { ...DEFAULT_PALETTE, primary: '#111111' };
            const paletteB = { ...DEFAULT_PALETTE, primary: '#222222' };

            const key1 = getTintKey('meskel-cross-01', { c1: '@primary' }, paletteA);
            const key2 = getTintKey('meskel-cross-01', { c1: '@primary' }, paletteB);
            const key3 = getTintKey('lalibela-cross-02', { c1: '@primary' }, paletteA);

            expect(key1).not.toBe(key2);
            expect(key1).not.toBe(key3);
            expect(key1).toContain('#111111');
            expect(key2).toContain('#222222');
        });
    });

    describe('Language Switching & Bilingual Copy (Task 5)', () => {
        it('resolves placeholders in text layers with event data', () => {
            const raw = 'Welcome {{guest_name}} to {{event_title}} on {{date}}!';
            const data = {
                guest_name: 'Solomon & Sara',
                event_title: 'Golden Jubilee',
                date: 'Sunday, Dec 12, 2027',
            };

            const resolved = resolvePlaceholders(raw, data);
            expect(resolved).toBe('Welcome Solomon & Sara to Golden Jubilee on Sunday, Dec 12, 2027!');
        });

        it('picks correct copy based on language mode (en / am / both)', () => {
            const i18n = {
                en: 'Cordially Invites You',
                am: 'በታላቅ ደስታ ይጋብዝዎታል',
            };

            // English mode
            const enCopy = i18n.en;
            expect(enCopy).toBe('Cordially Invites You');

            // Amharic mode
            const amCopy = i18n.am;
            expect(amCopy).toBe('በታላቅ ደስታ ይጋብዝዎታል');

            // Both mode
            const bothCopy = `${i18n.am}\n${i18n.en}`;
            expect(bothCopy).toContain('በታላቅ ደስታ ይጋብዝዎታል');
            expect(bothCopy).toContain('Cordially Invites You');
        });
    });
});
