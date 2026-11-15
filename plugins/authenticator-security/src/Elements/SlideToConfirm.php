<?php

declare(strict_types=1);

namespace Cbox\AuthenticatorSecurity\Elements;

use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

/**
 * Slide to approve: `<slide-confirm label="…" @change="approve" :busy="…" :reset="$n" />`.
 *
 * The handler runs once, with `true`, when the thumb reaches the end of the track (or
 * VoiceOver activates the control). Releasing early does nothing. Rendered by
 * CboxSlideToConfirmRenderer on iOS.
 */
final class SlideToConfirm extends Element
{
    protected string $type = 'slide_confirm';

    /** @var array<string, bool|int|string> */
    private array $slideProps = [];

    private ?string $changeCallback = null;

    public static function make(): static
    {
        return new self;
    }

    /** @param array<string, mixed> $attrs */
    public function applyAttributes(array $attrs): void
    {
        foreach (['label', 'a11y-label' => 'a11y_label', 'a11y-hint' => 'a11y_hint'] as $attribute => $prop) {
            $attribute = is_int($attribute) ? $prop : $attribute;
            if (isset($attrs[$attribute]) && is_scalar($attrs[$attribute])) {
                $this->slideProps[$prop] = (string) $attrs[$attribute];
            }
        }
        foreach (['disabled', 'busy'] as $flag) {
            if (array_key_exists($flag, $attrs)) {
                $this->slideProps[$flag] = filter_var($attrs[$flag], FILTER_VALIDATE_BOOL);
            }
        }
        if (isset($attrs['reset']) && is_numeric($attrs['reset'])) {
            $this->slideProps['reset'] = (int) $attrs['reset'];
        }
    }

    public function onChange(string $method): static
    {
        $this->changeCallback = $method;

        return $this;
    }

    /** @return array<string, mixed> */
    protected function resolveProps(CallbackRegistry $registry): array
    {
        $props = $this->slideProps;
        if ($this->changeCallback !== null) {
            $props['on_change'] = $registry->register($this->changeCallback);
        }

        return $props;
    }

    /** @return array<string, mixed> */
    public function getStyle(): array
    {
        return [];
    }
}
