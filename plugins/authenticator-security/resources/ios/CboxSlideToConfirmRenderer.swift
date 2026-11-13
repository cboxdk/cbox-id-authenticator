import SwiftUI
import UIKit

/// Slide to approve.
///
/// An approval is the one action in this app that must never happen by accident: a tap
/// that lands while the screen is still settling, a pocket, a thumb on the way to Deny.
/// So it is a deliberate drag to the end of a track, not a press. Releasing short of the
/// end springs the thumb back and approves nothing.
///
/// Only the completed slide reaches PHP, as one TOGGLE_CHANGE(true) on `on_change`.
/// Device authentication (Face ID / passcode) still follows in PHP, so this control is a
/// guard against a mistaken touch, not the authorisation itself.
///
/// VoiceOver and Switch Control cannot perform a precise drag, so the control is one
/// accessible button whose default action (double-tap) completes it. That is deliberate
/// input too, and Face ID follows. Reduce Motion drops the spring.
///
/// Props: `label`, `a11y_label`, `a11y_hint`, `disabled`, `busy`, `reset` (an int; when
/// PHP changes it the thumb returns to the start, e.g. after a cancelled Face ID).
struct CboxSlideToConfirmRenderer: View {
    let node: NativeUINode

    @ObservedObject private var themeStore = NativeUITheme.shared
    @Environment(\.colorScheme) private var colorScheme
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var offset: CGFloat = 0
    @State private var dragging = false
    @State private var confirmed = false
    @State private var lastReset = 0
    @State private var crossedThreshold = false

    private let height: CGFloat = 64
    private let inset: CGFloat = 6

    var body: some View {
        let theme = themeStore.resolve(for: colorScheme)
        let p = node.props
        let label = p.getString("label", default: "Slide to approve")
        let a11yLabel = p.getString("a11y_label", default: label)
        let a11yHint = p.getString("a11y_hint")
        let disabled = p.getBool("disabled")
        let busy = p.getBool("busy")
        let reset = p.getInt("reset")
        let callback = p.getCallbackId("on_change")
        let thumb = height - inset * 2

        GeometryReader { geo in
            let travel = max(1, geo.size.width - thumb - inset * 2)
            let progress = min(1, max(0, offset / travel))

            ZStack(alignment: .leading) {
                Capsule()
                    .fill(theme.primary.opacity(colorScheme == .dark ? 0.22 : 0.10))
                    .overlay(Capsule().strokeBorder(theme.primary.opacity(0.28), lineWidth: 1))

                // The trail fills behind the thumb, so progress reads at a glance.
                Capsule()
                    .fill(theme.primary.opacity(0.85))
                    .frame(width: offset + thumb + inset * 2)
                    .opacity(offset > 0 ? 1 : 0)

                HStack(spacing: 6) {
                    Text(label)
                        .font(.system(size: 17, weight: .semibold))
                    if !busy && !confirmed {
                        Image(systemName: "chevron.forward.2")
                            .font(.system(size: 13, weight: .bold))
                    }
                }
                .foregroundStyle(theme.primary)
                .opacity(busy || confirmed ? 0 : 1 - Double(progress) * 1.4)
                .frame(maxWidth: .infinity)
                .padding(.leading, thumb * 0.6)
                .allowsHitTesting(false)

                Circle()
                    .fill(theme.primary)
                    .overlay(
                        Group {
                            if busy || confirmed {
                                ProgressView().tint(theme.onPrimary)
                            } else {
                                Image(systemName: "arrow.forward")
                                    .font(.system(size: 20, weight: .bold))
                                    .foregroundStyle(theme.onPrimary)
                            }
                        }
                    )
                    .shadow(color: .black.opacity(0.18), radius: 4, y: 2)
                    .frame(width: thumb, height: thumb)
                    .scaleEffect(dragging ? 1.06 : 1)
                    .offset(x: inset + offset)
                    .gesture(
                        DragGesture(minimumDistance: 4)
                            .onChanged { value in
                                guard !disabled, !busy, !confirmed else { return }
                                if !dragging {
                                    dragging = true
                                    UIImpactFeedbackGenerator(style: .light).impactOccurred()
                                }
                                offset = min(travel, max(0, value.translation.width))
                                let past = offset >= travel * 0.92
                                if past != crossedThreshold {
                                    crossedThreshold = past
                                    UISelectionFeedbackGenerator().selectionChanged()
                                }
                            }
                            .onEnded { _ in
                                guard dragging else { return }
                                dragging = false
                                if offset >= travel * 0.92 {
                                    complete(travel: travel, callback: callback)
                                } else {
                                    springBack()
                                }
                            }
                    )
            }
            .frame(height: height)
        }
        .frame(height: height)
        .opacity(disabled && !busy ? 0.45 : 1)
        .onAppear { lastReset = reset }
        .onChange(of: reset) { _, new in
            if new != lastReset {
                lastReset = new
                confirmed = false
                springBack()
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(Text(a11yLabel))
        .modifier(CboxSlideHint(hint: a11yHint))
        .accessibilityAddTraits(.isButton)
        .accessibilityValue(Text(busy || confirmed ? "…" : ""))
        .accessibilityAction {
            guard !disabled, !busy, !confirmed else { return }
            complete(travel: nil, callback: callback)
        }
    }

    private func complete(travel: CGFloat?, callback: Int) {
        confirmed = true
        crossedThreshold = false
        if let travel {
            withAnimation(reduceMotion ? nil : .easeOut(duration: 0.15)) { offset = travel }
        }
        UINotificationFeedbackGenerator().notificationOccurred(.success)
        if callback != 0 {
            NativeElementBridge.sendToggleChangeEvent(callback, nodeId: node.id, value: true)
        }
    }

    private func springBack() {
        crossedThreshold = false
        withAnimation(reduceMotion ? nil : .spring(response: 0.35, dampingFraction: 0.75)) { offset = 0 }
    }
}

private struct CboxSlideHint: ViewModifier {
    let hint: String
    func body(content: Content) -> some View {
        if hint.isEmpty { content } else { content.accessibilityHint(Text(hint)) }
    }
}
