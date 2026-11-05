<?php

declare(strict_types=1);

namespace App\Layouts;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\About;
use App\NativeComponents\AccountDetail;
use App\NativeComponents\AddCode;
use App\NativeComponents\ApprovalDetail;
use App\NativeComponents\CodeBackup;
use App\NativeComponents\CodeDetail;
use App\NativeComponents\Codes;
use App\NativeComponents\Connect;
use App\NativeComponents\Licenses;
use App\NativeComponents\Settings;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/** Native navigation shared by the code vault, approvals and account settings. */
class AppLayout extends NativeLayout
{
    public function usesNativeChrome(): bool
    {
        return true;
    }

    /** Cbox approvals lead; verification codes remain available without a Cbox account. */
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        $bar = TabBar::make()
            ->add(Tab::link('Cbox ID', '/approvals', ios: Ios::CheckmarkShield, android: Android::Shield)
                ->active($screen instanceof Connect && ! $screen->openedFromSettings()))
            ->add(Tab::link(__('Codes'), '/codes', ios: Ios::Key, android: Android::Lock))
            ->add(Tab::link(__('Settings'), '/settings', ios: Ios::Gearshape, android: Android::Settings)
                ->active($screen instanceof AccountDetail || $screen instanceof About || $screen instanceof Licenses));

        // This preserves explicit ownership for deep-link routes outside /settings.
        // The builder's URL highlighter only honors active flags on adopted elements.
        return TabBar::fromElement($bar->toElement());
    }

    /**
     * A title, and a back button only where there is something to go back to.
     *
     * The detail screen is pushed from the list, so it gets one. The three tab roots are
     * not pushed onto anything — a back button there would either do nothing or leave the
     * tab, both of which are worse than its absence.
     */
    public function navBar(NativeComponent $screen): ?NavBar
    {
        return match (true) {
            $screen instanceof Codes => NavBar::make()->title(__('Codes'))->displayMode('inline')
                ->action(NavAction::make('backup')->icon('ellipsis')->a11yLabel(__('More options'))->items([
                    NavAction::make('backup')->icon('lock.doc')->label(__('Encrypted backup'))->press('backup'),
                ]))
                ->action(NavAction::make('add')->icon('plus')->a11yLabel(__('Add code'))->press('add')),
            $screen instanceof Connect => NavBar::make()->title(__('Connect an account'))->displayMode('inline')->back(),
            $screen instanceof AddCode => NavBar::make()->title(__('Add code'))->back(),
            $screen instanceof CodeDetail => NavBar::make()->title(__('Code details'))->back(),
            $screen instanceof CodeBackup => NavBar::make()->title(__('Encrypted backup'))->back(),
            $screen instanceof AccountDetail => NavBar::make()->title(__('Account'))->back(),
            $screen instanceof ApprovalDetail => NavBar::make()->title(__('Request'))->back(),
            $screen instanceof Licenses => NavBar::make()->title(__('Licenses'))->back(),
            $screen instanceof About => NavBar::make()->title(__('Privacy and help'))->back(),
            $screen instanceof Settings => NavBar::make()->title(__('Settings'))->displayMode('large')
                ->action(NavAction::make('add')->icon('plus')->a11yLabel(__('Add another account'))->press('goAddAccount')),
            default => NavBar::make()->title('Cbox ID')->displayMode('large')
                ->action(NavAction::make('add')->icon('plus')->a11yLabel(__('Connect an account'))->press('addAccount')),
        };
    }
}
