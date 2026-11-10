import { describe, it, expect, vi } from 'vitest';
import { wrapTextLines, renderTextLayer, resolvePlaceholders } from '@/services/renderDesign';
import { wrapTextLines as nodeWrapTextLines } from '../../scripts/node_card_renderer.js';

describe('Card Text Boundary Constraints & Word-Wrapping', () => {
    // Mock canvas 2D context for text measurement
    const createMockCtx = () => {
        let currentFont = '400 32px sans-serif';
        const fillTextCalls: Array<{ text: string; x: number; y: number }> = [];
        const clipCalls: Array<{ x: number; y: number; w: number; h: number }> = [];

        const ctx = {
            get font() {
                return currentFont;
            },
            set font(val) {
                currentFont = val;
            },
            measureText: (text: string) => {
                // Approximate width based on current font size
                const match = currentFont.match(/(\d+)px/);
                const size = match ? parseInt(match[1]) : 30;
                // Roughly 0.6 * size per character
                return { width: text.length * (size * 0.58) };
            },
            save: vi.fn(),
            restore: vi.fn(),
            beginPath: vi.fn(),
            rect: vi.fn((x, y, w, h) => {
                clipCalls.push({ x, y, w, h });
            }),
            clip: vi.fn(),
            fillText: vi.fn((text, x, y) => {
                fillTextCalls.push({ text, x, y });
            }),
            strokeText: vi.fn(),
            textAlign: 'center',
            textBaseline: 'top',
            fillStyle: '#000000',
            strokeStyle: '#000000',
            lineWidth: 1,
            shadowColor: '',
            shadowBlur: 0,
            shadowOffsetX: 0,
            shadowOffsetY: 0,
            _fillTextCalls: fillTextCalls,
            _clipCalls: clipCalls,
        } as unknown as CanvasRenderingContext2D & {
            _fillTextCalls: typeof fillTextCalls;
            _clipCalls: typeof clipCalls;
        };

        return ctx;
    };

    describe('wrapTextLines', () => {
        it('wraps long English sentences at word boundaries so no line exceeds maxWidth', () => {
            const ctx = createMockCtx();
            ctx.font = '400 30px sans-serif'; // ~17.4px per char
            const text = 'Cordially Inviting His Excellency Ambassador Girma Birru & Family';
            const maxWidth = 300; // fits ~17 chars per line

            const lines = wrapTextLines(ctx, text, maxWidth);
            expect(lines.length).toBeGreaterThan(1);
            for (const line of lines) {
                expect(ctx.measureText(line).width).toBeLessThanOrEqual(maxWidth);
            }
        });

        it('wraps Amharic sentences at word boundaries so no line exceeds maxWidth', () => {
            const ctx = createMockCtx();
            ctx.font = '400 30px sans-serif';
            const text = 'ክቡር አቶ ተስፋዬ ገብረሚካኤል እና ባለቤታቸው ወ/ሮ ብርቱካን';
            const maxWidth = 350;

            const lines = wrapTextLines(ctx, text, maxWidth);
            expect(lines.length).toBeGreaterThan(1);
            for (const line of lines) {
                expect(ctx.measureText(line).width).toBeLessThanOrEqual(maxWidth);
            }
        });

        it('breaks exceptionally long unbreakable strings character-by-character to prevent overflow', () => {
            const ctx = createMockCtx();
            ctx.font = '400 30px sans-serif';
            const text = 'UNBROKEN_LONG_GUEST_NAME_WITHOUT_SPACES_OR_PUNCTUATION';
            const maxWidth = 200;

            const lines = wrapTextLines(ctx, text, maxWidth);
            expect(lines.length).toBeGreaterThan(1);
            for (const line of lines) {
                expect(ctx.measureText(line).width).toBeLessThanOrEqual(maxWidth);
            }
        });

        it('preserves intentional explicit line breaks', () => {
            const ctx = createMockCtx();
            const text = 'Line 1\nLine 2\nLine 3';
            const lines = wrapTextLines(ctx, text, 800);
            expect(lines).toEqual(['Line 1', 'Line 2', 'Line 3']);
        });

        it('has matching behavior between browser renderer and node renderer', () => {
            const ctx = createMockCtx();
            const text = 'Cordially Inviting Almaz Bekele & Dawit Kebede to our Celebration';
            const maxWidth = 400;

            const browserLines = wrapTextLines(ctx, text, maxWidth);
            const nodeLines = nodeWrapTextLines(ctx, text, maxWidth);
            expect(browserLines).toEqual(nodeLines);
        });
    });

    describe('renderTextLayer AutoFit & Safety Boundaries', () => {
        it('auto-fits font size down so long text fits inside designated width and height', () => {
            const ctx = createMockCtx();
            const layer = {
                id: 'text-opening',
                type: 'text' as const,
                x: 120,
                y: 200,
                width: 840,
                height: 56,
                text: 'Cordially Inviting His Excellency Ambassador Girma Birru & Family',
                fontSize: 36,
                fontWeight: 400,
                fill: '#111827',
                align: 'center' as const,
            };

            const palette = {
                bg: '#FAF7F2',
                surface: '#FFFFFF',
                primary: '#800020',
                secondary: '#D4AF37',
                accent: '#C5A059',
                ink: '#1F2937',
                muted: '#6B7280',
            };

            renderTextLayer(ctx, layer, layer.x, layer.y, layer.width, layer.height, 1, 'en', palette, {});

            // Assert clipping occurred around layer boundary
            expect(ctx.clip).toHaveBeenCalled();
            // Assert all drawn lines fit inside width
            for (const call of ctx._fillTextCalls) {
                expect(ctx.measureText(call.text).width).toBeLessThanOrEqual(layer.width);
            }
        });

        it('properly resolves {{guest_name}} in English and Amharic', () => {
            const enResolved = resolvePlaceholders('Special Invitation for {{guest_name}}', {
                guest_name: 'Almaz Bekele',
            }, 'en');
            expect(enResolved).toBe('Special Invitation for Almaz Bekele');

            const amResolved = resolvePlaceholders('ክቡር/ት {{guest_name}}', {
                guest_name: 'አልማዝ በቀለ',
            }, 'am');
            expect(amResolved).toBe('ክቡር/ት አልማዝ በቀለ');
        });
    });
});
