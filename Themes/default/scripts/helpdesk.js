/**
 * SimpleDesk Helpdesk - Main JavaScript
 * @package SimpleDesk
 * @version 2.1.5
 */

// SimpleDesk namespace
var SimpleDesk = SimpleDesk || {};

/**
 * Initialize SimpleDesk functionality.
 */
SimpleDesk.init = function() {
	// Bind quick ticket jump form
	var jumpForm = document.getElementById('shd_ticket_jump_form');
	if (jumpForm) {
		jumpForm.addEventListener('submit', function(e) {
			var input = this.querySelector('input[name="ticket"]');
			if (input && input.value.trim() === '') {
				e.preventDefault();
				input.focus();
			}
		});
	}
};

/**
 * Toggle visibility of a collapsible section.
 *
 * @param {string} sectionId The DOM element ID to toggle
 * @param {object} toggleLink The clicked link/button element
 */
SimpleDesk.toggleSection = function(sectionId, toggleLink) {
	var section = document.getElementById(sectionId);
	if (!section)
		return;

	if (section.style.display === 'none') {
		section.style.display = '';
		if (toggleLink)
			toggleLink.innerHTML = toggleLink.innerHTML.replace('&#9654;', '&#9660;');
	} else {
		section.style.display = 'none';
		if (toggleLink)
			toggleLink.innerHTML = toggleLink.innerHTML.replace('&#9660;', '&#9654;');
	}
};

/**
 * Quick reply toggle - show/hide the quick reply form.
 */
SimpleDesk.toggleQuickReply = function() {
	SimpleDesk.toggleSection('shd_quickreply_body');
};

/**
 * Confirm before performing a destructive action.
 *
 * @param {string} message Confirmation message
 * @param {string} url URL to navigate to if confirmed
 * @returns {boolean} Whether the action was confirmed
 */
SimpleDesk.confirmAction = function(message, url) {
	if (confirm(message)) {
		if (url)
			window.location.href = url;
		return true;
	}
	return false;
};

/**
 * Quote a reply into the quick reply form.
 *
 * @param {int} msgId The message ID to quote
 * @param {string} author The author's name
 */
SimpleDesk.quoteReply = function(msgId, author) {
	var replyBody = document.getElementById('msg_' + msgId);
	if (!replyBody)
		return;

	var bodyDiv = replyBody.querySelector('.shd_reply_body');
	if (!bodyDiv)
		return;

	// Get the text content (strip HTML tags for simple quoting)
	var text = bodyDiv.textContent || bodyDiv.innerText || '';
	text = text.trim();

	var textarea = document.querySelector('.shd_quickreply textarea[name="body"]');
	if (!textarea)
		return;

	// Build BBCode quote
	var quote = '[quote author=' + author + ']\n' + text + '\n[/quote]\n\n';

	// Append to textarea
	textarea.value += quote;
	textarea.focus();

	// Scroll to quick reply
	var quickReply = document.querySelector('.shd_quickreply');
	if (quickReply)
		quickReply.scrollIntoView({behavior: 'smooth'});
};

/**
 * Scroll to a specific reply message.
 *
 * @param {int} msgId The message ID to scroll to
 */
SimpleDesk.scrollToReply = function(msgId) {
	var element = document.getElementById('msg_' + msgId);
	if (element) {
		element.scrollIntoView({behavior: 'smooth'});
		// Briefly highlight
		element.style.transition = 'background-color 0.3s';
		element.style.backgroundColor = '#ffffcc';
		setTimeout(function() {
			element.style.backgroundColor = '';
		}, 2000);
	}
};

// Document ready
if (document.addEventListener) {
	document.addEventListener('DOMContentLoaded', SimpleDesk.init);
}
