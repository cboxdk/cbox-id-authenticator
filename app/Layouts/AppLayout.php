<?php

declare(strict_types=1);

namespace App\Layouts;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\ApprovalDetail;
use App\NativeComponents\Settings;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * The chrome the signed-in screens share.
 *
 * WHY A LAYOUT RATHER THAN CONTROLS ON EACH SCREEN
 * ------------------------------------------------
 * The approvals list used to end in a hand-rolled row of ghost buttons. It scrolled away
 * with the content, it had to reserve the home-indicator inset itself, and every screen
 * that wanted the same controls had to repeat them — Settings never did, so from there
 * the only way back was the OS gesture.
 *
 * A tab bar is chrome: the renderer pins it, keeps it while the list scrolls, and marks
 * which screen you are on. It is also what makes `safe-area` classes wrong on these
 * screens — the framework frees the edges the bars do not own, so a screen claiming them
 * as well is inset twice. See NativeComponent::buildChromeColumn().
 *
 * NOT EVERY SCREEN IS UNDER IT. Launch is a router that paints one frame; OAuthCallback
 * is a moment between the browser and the approvals list; Connect is the first-run screen
 * where both tabs would lead somewhere there is nothing yet. Those three stay chrome-less
 * and keep their own `safe-area`.
 */
class AppLayout extends NativeLayout
{
    /**
     * Two tabs, because there are two places to be.
     *
     * Adding an account is deliberately NOT one: it is an action taken occasionally from
     * Settings, and a permanent tab for it would give equal weight to something done once
     * per account and something done every time a prompt arrives.
     */
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->add(Tab::link('Requests', '/approvals', ios: Ios::Bell, android: Android::Notifications))
            ->add(Tab::link('Settings', '/settings', ios: Ios::Gearshape, android: Android::Settings));
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
            $screen instanceof ApprovalDetail => NavBar::make()->title('Request')->back(),
            $screen instanceof Settings => NavBar::make()->title('Settings'),
            default => NavBar::make()->title('Pending requests'),
        };
    }
}
