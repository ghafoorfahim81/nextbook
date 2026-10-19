import { router } from '@inertiajs/vue3'

// Pages only a signed-out visitor should see. The server already sends a
// signed-in user away from them (the `guest` middleware), but the browser's
// back button never asks the server: Inertia redraws the page straight from
// the history entry, so the login form written there before sign-in comes
// back as if the user were signed out.
const GUEST_PAGES = new Set([
    'Auth/Login',
    'Auth/Register',
    'Auth/ForgotPassword',
    'Auth/ResetPassword',
    'Auth/TwoFactorChallenge',
])

// Ask the server for the page instead. A signed-in user is redirected to the
// dashboard, and `replace` swaps that history entry for it, so going back
// again never lands on the login form; a signed-out user gets the form.
function recheck() {
    router.reload({ replace: true })
}

// Must run before the Inertia app mounts: Inertia adds its own popstate
// listener then, and this one has to run first to keep it from drawing the
// stale page at all — waiting until after it had drawn left the login form
// on screen until the redirect came back.
export function installGuestPageGuard() {
    window.addEventListener('popstate', (event) => {
        if (!GUEST_PAGES.has(event.state?.component)) return

        // The current page stays on screen while the server answers.
        event.stopImmediatePropagation()
        recheck()
    })

    // A whole document restored from the back-forward cache runs no script
    // and fires no popstate; only `pageshow` says it came back.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && GUEST_PAGES.has(window.history.state?.component)) {
            recheck()
        }
    })
}
