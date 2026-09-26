// Minimal pub-sub tracking whether an Inertia page navigation is in flight.
// Kept outside React so it can be driven from app.jsx's router listeners and
// consumed by LoadingScreen without prop drilling through every layout.
let active = false;
const listeners = new Set();

export function setNavigationLoading(value) {
    active = value;
    listeners.forEach((listener) => listener(active));
}

export function subscribeLoading(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}
