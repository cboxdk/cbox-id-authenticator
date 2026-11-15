<?php

declare(strict_types=1);

namespace Cbox\AuthenticatorSecurity\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

final class SlideToConfirm extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'slide_confirm';
    }
}
