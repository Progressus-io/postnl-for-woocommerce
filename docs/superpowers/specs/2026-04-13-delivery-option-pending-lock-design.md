# Delivery Option Pending Lock

**Date:** 2026-04-13
**Status:** Approved

## Problem

When a user clicks between delivery options (Morning, Evening, Standard) quickly in the blocks checkout, multiple `extensionCartUpdate` calls fire in flight simultaneously. WooCommerce's order totals update after each one resolves, causing the totals to flicker and potentially settle on the wrong value (race condition). The lag is noticeable and the interaction feels broken.

## Goal

Prevent rapid re-selection while a cart update is in flight. Lock the delivery options list as non-interactive until the current update resolves. This eliminates the race condition entirely rather than masking it.

## Scope

- **In scope:** The delivery day options list (`postnl_delivery_day_list`) in the blocks checkout component.
- **Out of scope:** The tab switcher (Delivery ↔ Pickup), the dropoff points component, the classic (non-blocks) checkout.

## Design

### State

Add a single `isPending` boolean to `postnl-delivery-day/block.js`:

```js
const [ isPending, setIsPending ] = useState( false );
```

### Locking behaviour

In `handleOptionChange`, set `isPending = true` before the `extensionCartUpdate` call and clear it in a `finally` block so it always resets — even on error:

```js
setIsPending( true );
try {
    await extensionCartUpdate( { ... } );
} catch ( error ) {
    // existing silent catch
} finally {
    setIsPending( false );
}
```

### Visual

Apply the pending state as a CSS class on the `<ul>` that wraps the options:

```jsx
<ul
    className={ `postnl_delivery_day_list postnl_list${ isPending ? ' postnl-updating' : '' }` }
    aria-busy={ isPending }
>
```

Two CSS rules added to `assets/css/fe-checkout.css`:

```css
.postnl_delivery_day_list {
    transition: opacity 0.15s ease;
}

.postnl_delivery_day_list.postnl-updating {
    opacity: 0.5;
    pointer-events: none;
}
```

The `transition` gives a smooth fade rather than an abrupt flash. `pointer-events: none` is the mechanism that prevents re-clicks. `aria-busy` communicates the pending state to assistive technology.

## Files Changed

| File | Change |
|------|--------|
| `client/checkout/postnl-delivery-day/block.js` | Add `isPending` state; set/clear around `extensionCartUpdate`; apply class and `aria-busy` to `<ul>` |
| `assets/css/fe-checkout.css` | Add transition and `.postnl-updating` rules |

## Error Handling

The `finally` block guarantees the lock clears on both success and error. The existing silent error catch is preserved — no regression.

## What Is Not Changing

- No debouncing. The lock itself prevents stacked requests.
- No change to the tab switcher or pickup points component.
- No change to the classic checkout.
- No change to how `extensionCartUpdate` is called or what data it sends.
