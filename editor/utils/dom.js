/**
 * Check whether the caret is horizontally at the edge of the container.
 *
 * @param  {Element} container Focusable element.
 * @param  {Boolean} reverse   Set to true to check left, false for right.
 * @return {Boolean}           True if at the edge, false if not.
 */
export function isHorizontalEdge( container, reverse ) {
	if ( [ 'INPUT', 'TEXTAREA' ].indexOf( container.tagName ) !== -1 ) {
		if ( container.selectionStart !== container.selectionEnd ) {
			return false;
		}

		if ( reverse ) {
			return container.selectionStart === 0;
		}

		return container.value.length === container.selectionStart;
	}

	if ( ! container.isContentEditable ) {
		return true;
	}

	const selection = window.getSelection();
	const range = selection.rangeCount ? selection.getRangeAt( 0 ) : null;

	if ( ! range || ! range.collapsed ) {
		return false;
	}

	const position = reverse ? 'start' : 'end';
	const order = reverse ? 'first' : 'last';
	const offset = range[ `${ position }Offset` ];

	let node = range.startContainer;

	if ( reverse && offset !== 0 ) {
		return false;
	}

	if ( ! reverse && offset !== node.textContent.length ) {
		return false;
	}

	while ( node !== container ) {
		const parentNode = node.parentNode;

		if ( parentNode[ `${ order }Child` ] !== node ) {
			return false;
		}

		node = parentNode;
	}

	return true;
}

/**
 * Holds the first caret boundary rectangle for subsequent calls.
 *
 * @type {DOMRect}
 */
let firstVerticalRect;

/**
 * Resets the first caret position.
 */
export function resetVerticalPosition() {
	firstVerticalRect = null;
}

/**
 * Check whether the caret is vertically at the edge of the container.
 *
 * @param  {Element} container Focusable element.
 * @param  {Boolean} reverse   Set to true to check top, false for bottom.
 * @return {Boolean}           True if at the edge, false if not.
 */
export function isVerticalEdge( container, reverse ) {
	if ( [ 'INPUT', 'TEXTAREA' ].indexOf( container.tagName ) !== -1 ) {
		return isHorizontalEdge( container, reverse );
	}

	if ( ! container.isContentEditable ) {
		return true;
	}

	const selection = window.getSelection();
	const range = selection.rangeCount ? selection.getRangeAt( 0 ) : null;

	if ( ! range || ! range.collapsed ) {
		return false;
	}

	// Adjust for empty containers.
	const rangeRect =
		range.startContainer.nodeType === window.Node.ELEMENT_NODE ?
		range.startContainer.getBoundingClientRect() :
		range.getClientRects()[ 0 ];

	if ( ! rangeRect ) {
		return false;
	}

	const buffer = rangeRect.height / 2;
	const editableRect = container.getBoundingClientRect();

	if ( ! firstVerticalRect ) {
		firstVerticalRect = rangeRect;
	}

	// Too low.
	if ( reverse && rangeRect.top - buffer > editableRect.top ) {
		return false;
	}

	// Too high.
	if ( ! reverse && rangeRect.bottom + buffer < editableRect.bottom ) {
		return false;
	}

	return true;
}

/**
 * Places the caret at start or end of a given element.
 *
 * @param {Element} container Focusable element.
 * @param {Boolean} reverse   True for end, false for start.
 */
export function placeCaretAtHorizontalEdge( container, reverse ) {
	const isInputOrTextarea = [ 'INPUT', 'TEXTAREA' ].indexOf( container.tagName ) !== -1;

	// Inputs and Textareas
	if ( isInputOrTextarea ) {
		container.focus();
		if ( reverse ) {
			container.selectionStart = 0;
			container.selectionEnd = 0;
		} else {
			container.selectionStart = container.value.length;
			container.selectionEnd = container.value.length;
		}
		return;
	}

	if ( ! container.isContentEditable ) {
		container.focus();
		return;
	}

	// Content editables
	const range = document.createRange();
	range.selectNodeContents( container );
	range.collapse( reverse );
	const sel = window.getSelection();
	sel.removeAllRanges();
	sel.addRange( range );
	container.focus();
}

/**
 * Polyfill.
 * Get a collapsed range for a given point.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Document/caretRangeFromPoint
 *
 * @param  {Document} doc The document of the range.
 * @param  {Float}    x   Horizontal position within the current viewport.
 * @param  {Float}    y   Vertical position within the current viewport.
 * @return {?Range}       The best range for the given point.
 */
function caretRangeFromPoint( doc, x, y ) {
	if ( doc.caretRangeFromPoint ) {
		return doc.caretRangeFromPoint( x, y );
	}

	if ( ! doc.caretPositionFromPoint ) {
		return null;
	}

	const point = doc.caretPositionFromPoint( x, y );
	const range = doc.createRange();

	range.setStart( point.offsetNode, point.offset );
	range.collapse( true );

	return range;
}

/**
 * Places the caret at the top or bottom of a given element.
 *
 * @param {Element} container Focusable element.
 * @param {Boolean} reverse   True for bottom, false for top.
 * @param {Boolean} noScroll  Set to true to prevent scrolling.
 */
export function placeCaretAtVerticalEdge( container, reverse, noScroll ) {
	const rect = firstVerticalRect;

	if ( ! rect || ! container.isContentEditable ) {
		placeCaretAtHorizontalEdge( container, reverse );
		return;
	}

	const buffer = rect.height / 2;
	const editableRect = container.getBoundingClientRect();
	const x = rect.left + ( rect.width / 2 );
	const y = reverse ? ( editableRect.bottom - buffer ) : ( editableRect.top + buffer );
	const selection = window.getSelection();

	// Temporary high z-index above toolbars.
	container.style.zIndex = '10000';

	const range = caretRangeFromPoint( document, x, y );

	container.style.zIndex = null;

	if ( ! range || ! container.contains( range.startContainer ) ) {
		if ( ! noScroll ) {
			// Might be out of view.
			// Easier than attempting to calculate manually.
			container.scrollIntoView( reverse );
			placeCaretAtVerticalEdge( container, reverse, true );
			return;
		}

		placeCaretAtHorizontalEdge( container, reverse );
		return;
	}

	selection.removeAllRanges();
	selection.addRange( range );
	container.focus();
	// Editable was already focussed, it goes back to old range...
	// This fixes it.
	selection.removeAllRanges();
	selection.addRange( range );
}

/**
 * Checks whether the user is on MacOS or not
 *
 * @return {Boolean}           Is Mac or Not
 */
export function isMac() {
	return window.navigator.platform.toLowerCase().indexOf( 'mac' ) !== -1;
}
