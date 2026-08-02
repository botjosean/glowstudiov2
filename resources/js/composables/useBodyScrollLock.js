// Shared across every BottomSheet instance: the body scroll lock and the
// Escape handler are global resources. Without reference counting, a sheet
// closing in the same tick another opens can write `overflow: ''` last and
// leave the page scrollable behind an open modal — this is what made a
// third simultaneously-mounted sheet on one page unsafe.
let lockCount = 0;
let previousOverflow = '';
const escapeStack = [];

function onGlobalKeydown(event) {
    if (event.key === 'Escape' && escapeStack.length > 0) {
        // Only the topmost sheet closes — previously every mounted sheet's
        // own listener fired independently, closing all of them at once.
        escapeStack[escapeStack.length - 1]();
    }
}

export function acquireBodyLock(close) {
    if (lockCount === 0) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onGlobalKeydown);
    }
    lockCount += 1;
    escapeStack.push(close);
}

export function releaseBodyLock(close) {
    const index = escapeStack.lastIndexOf(close);
    if (index !== -1) escapeStack.splice(index, 1);

    lockCount = Math.max(0, lockCount - 1);
    if (lockCount === 0) {
        document.body.style.overflow = previousOverflow;
        window.removeEventListener('keydown', onGlobalKeydown);
    }
}
