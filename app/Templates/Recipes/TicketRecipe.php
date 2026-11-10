<?php

namespace App\Templates\Recipes;

class TicketRecipe extends AbstractRecipe
{
    public function getId(): string
    {
        return 'ticket';
    }

    public function getName(): string
    {
        return 'Ticket';
    }

    public function getDescription(): string
    {
        return 'Vintage ceremonial admission pass with perforated stub, dashed dividing notch, and prominent date stamp.';
    }

    public function build(RecipeSpec $spec): array
    {
        $fonts = $spec->getFontRoles();
        $ticketOrnament = $spec->ornamentId ?: match ($spec->category) {
            'corporate', 'graduation' => 'ticket-notch-frame',
            'birthday' => 'sparkle-trio',
            default => 'badge-seal',
        };
        $divider = $spec->dividerOrnament ?: $this->defaultDividerOrnament($spec->category);

        $layers = [
            // 1. Non-text: Ticket outer bordered card surface
            $this->makeShapeLayer('shape-ticket-card', 'rect', 96, 96, 888, 1336, [
                'fill' => '@surface',
                'stroke' => '@secondary',
                'strokeWidth' => 2,
                'cornerRadius' => 24,
            ]),

            // 2. Non-text: Perforated/dashed divider separating stub
            $this->makeShapeLayer('shape-ticket-dashed-divider', 'line', 120, 1056, 840, 4, [
                'stroke' => '@muted',
                'strokeWidth' => 2,
            ]),

            // 3. Non-text: Header ticket ornament
            $this->makeOrnamentLayer('ornament-ticket-header', $ticketOrnament, 440, 128, 200, 104, [
                'tint' => ['c1' => '@primary', 'c2' => '@secondary', 'c3' => '@accent'],
            ]),

            // Text 1: Opening line
            $this->makeTextLayer(
                'text-opening',
                $spec->opening,
                144,
                248,
                792,
                48,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '600', 'letterSpacing' => 1]
            ),

            // Text 2: Main Event Title
            $this->makeTextLayer(
                'text-title',
                $spec->title,
                144,
                304,
                792,
                160,
                $fonts['display'],
                84,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '700', 'autoFit' => true]
            ),

            // Text 3: Host Names
            $this->makeTextLayer(
                'text-hosts',
                $spec->hostNames,
                144,
                472,
                792,
                48,
                $fonts['body'],
                28,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // Text 4: Invitation line
            $this->makeTextLayer(
                'text-invitation',
                $spec->invitation,
                144,
                528,
                792,
                72,
                $fonts['body'],
                32,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),

            // 4. Non-text: Middle divider ornament
            $this->makeOrnamentLayer('ornament-divider', $divider, 420, 616, 240, 40, [
                'tint' => ['c1' => '@secondary', 'c2' => '@primary', 'c3' => '@accent'],
            ]),

            // Text 5: Details (Date, Time, Venue)
            $this->makeTextLayer(
                'text-details',
                $spec->details,
                144,
                672,
                792,
                208,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400', 'lineHeight' => 1.45]
            ),

            // Text 6: Closing note
            $this->makeTextLayer(
                'text-closing',
                $spec->closing,
                144,
                904,
                792,
                56,
                $fonts['script_or_accent'],
                30,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '400']
            ),

            // --- STUB SECTION ---

            // Text 7: Stub header
            $this->makeTextLayer(
                'text-stub-header',
                $spec->locale === 'am' ? 'የክብር መግቢያ ትኬት' : 'OFFICIAL ADMISSION PASS',
                144,
                1088,
                792,
                48,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@primary', 'fontWeight' => '600', 'letterSpacing' => 1]
            ),

            // Text 8: RSVP
            $this->makeTextLayer(
                'text-rsvp',
                $spec->rsvp,
                144,
                1152,
                792,
                80,
                $fonts['body'],
                30,
                $spec,
                ['fill' => '@ink', 'fontWeight' => '400']
            ),
        ];

        return $this->wrapDesign($spec, $layers);
    }
}
