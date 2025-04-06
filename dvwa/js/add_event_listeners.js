// These functions need to be called after the content they reference
// has been added to the page otherwise they will fail.

/**
 * Attaches click event listeners to buttons for opening URLs in a popup.
 * @example
 * addEventListeners()
 * No direct return value; interacts with DOM elements
 * @param {void} - No parameters are needed.
 * @returns {void} No return value; function modifies elements directly.
 * @description
 *   - Retrieves URL from button's data attribute and opens it in a popup.
 *   - Assumes existence of `popUp` function for handling URL display.
 *   - Does nothing if target buttons are not found in the DOM.
 *   - Looks specifically for buttons with IDs 'source_button' and 'help_button'.
 */
function addEventListeners() {
	var source_button = document.getElementById ("source_button");

	if (source_button) {
		source_button.addEventListener("click", function() {
			var url=source_button.dataset.sourceUrl;
			popUp (url);
		});
	}

	var help_button = document.getElementById ("help_button");

	if (help_button) {
		help_button.addEventListener("click", function() {
			var url=help_button.dataset.helpUrl;
			popUp (url);
		});
	}
}

addEventListeners();
