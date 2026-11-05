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
use App\NativeComponents\Licenses;
use App\NativeComponents\Settings;
use App\Services\AppSecurity;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/** Native navigation shared by the code vault, approvals and account settings. */
class AppLayout extends NativeLayout
{
    /** Adding a Cbox account is an action under Settings; codes work without one. */
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        if (($screen instanceof About || $screen instanceof Licenses) && ! app(AppSecurity::class)->hasSecrets()) {
            return null;
        }

        return TabBar::make()
            ->add(Tab::link(__('Codes'), '/codes', ios: Ios::Key, android: Android::Lock))
            ->add(Tab::link(__('Requests'), '/approvals', ios: Ios::Bell, android: Android::Notifications))
            ->add(Tab::link(__('Settings'), '/settings', ios: Ios::Gearshape, android: Android::Settings));
    }

    /**
     * A title, and a back button only where there is something to go back to.
     *
     * The detail screen is pushed from the list, so it gets one. The two tab roots are
     * not pushed onto anything — a back button there would either do nothing or leave the
     * tab, both of which are worse than its absence.
     */
    public function navBar(NativeComponent $screen): ?NavBar
    {
        return match (true) {
            $screen instanceof Codes => NavBar::make()->title(__('Verification codes')),
            $screen instanceof AddCode => NavBar::make()->title(__('Add code'))->back(),
            $screen instanceof CodeDetail => NavBar::make()->title(__('Code details'))->back(),
            $screen instanceof CodeBackup => NavBar::make()->title(__('Encrypted backup'))->back(),
            $screen instanceof AccountDetail => NavBar::make()->title(__('Account'))->back(),
            $screen instanceof ApprovalDetail => NavBar::make()->title(__('Request'))->back(),
            $screen instanceof Licenses => NavBar::make()->title(__('Licenses'))->back(),
            $screen instanceof About => NavBar::make()->title(__('Privacy and help'))->back(),
            $screen instanceof Settings => NavBar::make()->title(__('Settings')),
            default => NavBar::make()->title(__('Pending requests')),
        };
    }
}
